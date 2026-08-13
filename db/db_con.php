<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'roadranger');
define('DB_USER', 'root');
define('DB_PASS', '');

function db_connect()
{
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        $conn = new PDO($dsn, DB_USER, DB_PASS, $options);

        if (session_status() === PHP_SESSION_ACTIVE) {
            
            
            if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 0) {
                try {
                    $updateHeartbeat = $conn->prepare("UPDATE users SET last_active = NOW() WHERE user_id = ?");
                    $updateHeartbeat->execute([$_SESSION['user_id']]);
                } catch (\PDOException $e) {
                    
                    error_log("Heartbeat update failed: " . $e->getMessage());
                }
            }
        }

        return $conn;

    } catch (\PDOException $e) {
        die('Database connection failed: ' . $e->getMessage());
    }
}

function find_user_by_email($conn, $email)
{
    $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function find_user_by_id($conn, $user_id)
{
    $stmt = $conn->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function create_user($conn, $email, $first_name, $last_name, $password, $birthday, $age_group, $initial_diff, $phone, $gender) {
    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $current_date = date('Y-m-d');
        $status = 'active';
        
        $username = $email; 

        $sql = "INSERT INTO users (
                    first_name, 
                    last_name, 
                    username, 
                    email, 
                    password, 
                    gender, 
                    phone, 
                    created_at, 
                    status, 
                    birthday, 
                    age_group, 
                    current_difficulty, 
                    is_admin
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $first_name,
            $last_name,
            $username,
            $email,
            $hashed_password,
            $gender,
            $phone,
            $current_date,
            $status,
            $birthday,
            $age_group,
            $initial_diff
        ]);
        
        $userId = $conn->lastInsertId();
        
        return [
            'user_id' => $userId,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'is_admin' => 0
        ];
    } catch (PDOException $e) {
        die("Registration Database Error: " . $e->getMessage());
        return false;
    }
}

function complete_user_profile($conn, $user_id, $first_name, $last_name, $gender, $phone, $date_of_birth = null)
{
    $stmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, gender = ?, phone = ?, birthday = ? WHERE user_id = ?');
    return $stmt->execute([$first_name, $last_name, $gender, $phone, $date_of_birth, $user_id]);
}

function is_profile_complete($user)
{
    return !empty($user['first_name']) && !empty($user['last_name']) && !empty($user['gender']) && !empty($user['phone']);
}

function login_user($user)
{
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    
    $_SESSION['is_admin'] = (int)$user['is_admin'];
}

function logout_user()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}




function is_admin_locked($conn, $user_id)
{
    $stmt = $conn->prepare('SELECT account_locked_until FROM users WHERE user_id = ? AND is_admin = 1');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['account_locked_until'])) {
        return false;
    }
    
    $lockedUntil = strtotime($user['account_locked_until']);
    if ($lockedUntil > time()) {
        return true;
    }
    
    
    $stmt = $conn->prepare('UPDATE users SET account_locked_until = NULL, login_attempts = 0 WHERE user_id = ?');
    $stmt->execute([$user_id]);
    
    return false;
}


function record_failed_login($conn, $email)
{
    $stmt = $conn->prepare('
        UPDATE users 
        SET login_attempts = login_attempts + 1,
            last_failed_login = NOW()
        WHERE email = ?
    ');
    $stmt->execute([$email]);
    
    
    $stmt = $conn->prepare('SELECT user_id, is_admin, login_attempts FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && (int)$user['is_admin'] === 1 && (int)$user['login_attempts'] >= 5) {
        $lockUntil = date('Y-m-d H:i:s', time() + 1800); 
        $stmt = $conn->prepare('UPDATE users SET account_locked_until = ? WHERE user_id = ?');
        $stmt->execute([$lockUntil, $user['user_id']]);
        
        return ['locked' => true, 'attempts' => (int)$user['login_attempts']];
    }
    
    return ['locked' => false, 'attempts' => (int)($user['login_attempts'] ?? 0)];
}


function reset_login_attempts($conn, $user_id)
{
    $stmt = $conn->prepare('
        UPDATE users 
        SET login_attempts = 0,
            last_failed_login = NULL,
            account_locked_until = NULL
        WHERE user_id = ?
    ');
    return $stmt->execute([$user_id]);
}


function enable_admin_2fa($conn, $user_id)
{
    $stmt = $conn->prepare('UPDATE users SET two_factor_enabled = 1 WHERE user_id = ? AND is_admin = 1');
    return $stmt->execute([$user_id]);
}


function disable_admin_2fa($conn, $user_id)
{
    $stmt = $conn->prepare('UPDATE users SET two_factor_enabled = 0 WHERE user_id = ? AND is_admin = 1');
    return $stmt->execute([$user_id]);
}


function find_user_by_google_id($conn, $googleId)
{
    $stmt = $conn->prepare('SELECT * FROM users WHERE google_id = ? LIMIT 1');
    $stmt->execute([$googleId]);
    return $stmt->fetch();
}


function update_user_oauth($conn, $user_id, $googleId, $provider = 'google')
{
    $stmt = $conn->prepare('UPDATE users SET google_id = ?, oauth_provider = ?, is_email_verified = 1 WHERE user_id = ?');
    return $stmt->execute([$googleId, $provider, $user_id]);
}


function create_user_from_oauth($conn, $email, $firstName, $lastName, $googleId, $provider = 'google')
{
    try {
        $username = $email;
        $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $currentDate = date('Y-m-d');

        $sql = "INSERT INTO users (
                    first_name, 
                    last_name, 
                    username, 
                    email, 
                    password, 
                    google_id,
                    oauth_provider,
                    is_email_verified,
                    created_at, 
                    status, 
                    age_group, 
                    current_difficulty, 
                    is_admin
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 'active', 'college_adult', 'hard', 0)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $firstName,
            $lastName,
            $username,
            $email,
            $password,
            $googleId,
            $provider,
            $currentDate
        ]);

        $userId = $conn->lastInsertId();

        return [
            'success' => true,
            'user_id' => $userId,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_admin' => 0
        ];
    } catch (PDOException $e) {
        error_log("OAuth User Creation Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}


function log_admin_activity($conn, $admin_id, $action, $details = '')
{
    try {
        $stmt = $conn->prepare('
            INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        
        return $stmt->execute([
            $admin_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        error_log("Admin Activity Log Error: " . $e->getMessage());
        return false;
    }
}

?>
