<?php
    session_start();
    require_once __DIR__ . '/../db/db_con.php';
    
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    
    require_once __DIR__ . '/../vendor/autoload.php'; 

    $active_tab = 'signin';
    $conn = db_connect();
    $signin_email = '';
    $signup_email = '';
    $first_name = '';
    $last_name = '';
    $signup_phone = '';
    $signup_gender = '';
    $birthday = '';
    $signin_error = '';
    $signin_success = ''; 
    $signup_error = '';

    function determine_user_tier($birthday_string) {
        $birthDate = new DateTime($birthday_string);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;

        $age_group = 'college_adult';
        $difficulty = 'hard';

        if ($age >= 5 && $age <= 11) {
            $age_group = 'elementary';
            $difficulty = 'easy';
        } elseif ($age >= 12 && $age <= 17) {
            $age_group = 'highschool';
            $difficulty = 'medium';
        }

        return [
            'age_group' => $age_group,
            'difficulty' => $difficulty
        ];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form_type = $_POST['form_type'] ?? 'signin';
        
        if ($form_type === 'signin') {
            $active_tab = 'signin';
            $signin_email = trim($_POST['email'] ?? '');
            $login_method = $_POST['login_method'] ?? 'password'; 

            if ($signin_email === '') {
                $signin_error = 'Please enter your email address.';
            } elseif (!filter_var($signin_email, FILTER_VALIDATE_EMAIL)) {
                $signin_error = 'Please enter a valid email address.';
            } else {
                $user = find_user_by_email($conn, $signin_email);

                if (!$user) {
                    $signin_error = 'No account associated with that email address.';
                } else {

                    if ($login_method === 'password') {
                        $password = $_POST['password'] ?? '';
                        if ($password === '') {
                            $signin_error = 'Please enter your password.';
                        } elseif (!password_verify($password, $user['password'])) {
                            $signin_error = 'Invalid email or password.';
                        } else {
                            login_user($user);
                            if (isset($user['is_admin']) && (int)$user['is_admin'] === 1) {
                                header('Location: admin/dashboard.php');
                            } else {
                                header('Location: users/dashboard.php');
                            }
                            exit;
                        }
                    } 

                    else if ($login_method === 'magic_link') {
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                        try {

                            $update_query = "UPDATE users SET login_token = :token, token_expires_at = :expires WHERE user_id = :user_id";
                            $stmt = $conn->prepare($update_query);
                            $result = $stmt->execute([
                                ':token'   => $token,
                                ':expires' => $expires,
                                ':user_id' => $user['user_id']
                            ]);
                            
                            if ($result) {
                                $mail = new PHPMailer(true);
                                $mail->isSMTP();
                                $mail->Host       = 'smtp.gmail.com'; 
                                $mail->SMTPAuth   = true;
                                $mail->Username   = 'your-roadranger-email@gmail.com'; 
                                $mail->Password   = 'your-app-password'; 
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port       = 587;

                                $mail->setFrom('your-roadranger-email@gmail.com', 'RoadRangers Platform');
                                $mail->addAddress($user['email']);

                                $magicLink = "http://" . $_SERVER['HTTP_HOST'] . "/roadranger(2)/pages/verify.php?token=" . $token;

                                $mail->isHTML(true);
                                $mail->Subject = 'Your Secure Access Link to RoadRangers';
                                $mail->Body    = "Hello " . htmlspecialchars($user['first_name']) . ",<br><br>
                                                  Click the link below to verify your identity and log into your dashboard. This link expires in 15 minutes:<br><br>
                                                  <a href='{$magicLink}' style='padding: 10px 15px; background-color: #667eea; color: white; text-decoration: none; border-radius: 5px; display: inline-block;'><strong>Login to RoadRangers</strong></a><br><br>
                                                  If you did not request this, you can safely ignore this email.";

                                $mail->send();
                                $signin_success = 'A secure login link has been dispatched to your inbox!';
                            } else {
                                $signin_error = 'Database tracking execution failed. Please try again.';
                            }
                        } catch (Exception $e) {
                            $signin_error = "System notification failed. Error: {$e->getMessage()}";
                        }
                    }
                }
            }
        } else {
            $active_tab = 'signup';
            $signup_email = trim($_POST['email'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $signup_phone = trim($_POST['phone_number'] ?? '');
            $signup_gender = $_POST['gender'] ?? ''; 
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $birthday = $_POST['birthday'] ?? ''; 
            $terms = $_POST['terms'] ?? '';

            if ($signup_email === '' || $first_name === '' || $last_name === '' || $password === '' || $confirm_password === '' || $birthday === '' || $signup_phone === '' || $signup_gender === '') {
                $signup_error = 'All fields are required.';
            } elseif (!filter_var($signup_email, FILTER_VALIDATE_EMAIL)) {
                $signup_error = 'Please enter a valid email address.';
            } elseif ($password !== $confirm_password) {
                $signup_error = 'Passwords do not match.';
            } elseif (strlen($password) < 8) {
                $signup_error = 'Password must be at least 8 characters.';
            } elseif ($terms !== '1') {
                $signup_error = 'You must agree to the terms to create an account.';
            } else {
                if (find_user_by_email($conn, $signup_email)) {
                    $signup_error = 'An account with that email already exists.';
                } else {
                    $tier = determine_user_tier($birthday);
                    $age_group = $tier['age_group'];
                    $initial_diff = $tier['difficulty'];

                    $user = create_user($conn, $signup_email, $first_name, $last_name, $password, $birthday, $age_group, $initial_diff, $signup_phone, $signup_gender);
                    
                    if ($user !== false) {
                        login_user($user);
                        header('Location: users/dashboard.php');
                        exit;
                    }
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login-SignUp | RoadRangers</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; justify-content: center; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .auth-container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 100%; max-width: 450px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #333; margin-bottom: 5px; }
        .header p { color: #999; font-size: 14px; }
        .tabs { display: flex; margin-bottom: 30px; border-bottom: 2px solid #eee; }
        .tab { flex: 1; text-align: center; padding: 15px; cursor: pointer; font-weight: 600; color: #999; transition: all 0.3s; }
        .tab.active { border-bottom: 3px solid #667eea; color: #667eea; }
        .tab:hover { color: #667eea; }
        form { display: none; flex-direction: column; }
        form.active { display: flex; }
        input, select { margin-bottom: 15px; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; background: white; transition: border-color 0.3s; }
        input:focus, select:focus { outline: none; border-color: #667eea; }
        button.primary { padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; transition: transform 0.2s; margin-bottom: 15px; }
        button.primary:hover { transform: translateY(-2px); }
        .error { background-color: #ffebee; color: #c62828; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; border-left: 4px solid #d32f2f; }
        .success { background-color: #e8f5e9; color: #2e7d32; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; border-left: 4px solid #4caf50; }
        label { font-size: 14px; margin-bottom: 6px; font-weight: 600; color: #333; }
        .terms-container { display: flex; align-items: center; gap: 8px; margin-bottom: 15px; }
        .terms-container input { margin-bottom: 0; width: auto; }
        .terms-container label { margin-bottom: 0; font-weight: normal; font-size: 13px; }
        .method-toggle { display: flex; gap: 10px; margin-bottom: 15px; }
        .method-btn { flex: 1; padding: 8px; font-size: 12px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9; cursor: pointer; text-align: center; font-weight: 600; color: #666; }
        .method-btn.active { background: #667eea; color: white; border-color: #667eea; }
        .password-field-wrapper { display: flex; flex-direction: column; }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="header">
        <h1>RoadRanger</h1>
    </div>

    <div class="tabs">
        <div class="tab <?php echo $active_tab === 'signin' ? 'active' : ''; ?>" onclick="switchTab('signin')">Sign In</div>
        <div class="tab <?php echo $active_tab === 'signup' ? 'active' : ''; ?>" onclick="switchTab('signup')">Sign Up</div>
    </div>

    <form id="signin-form" class="<?php echo $active_tab === 'signin' ? 'active' : ''; ?>" method="POST" action="">
        <input type="hidden" name="form_type" value="signin">
        <input type="hidden" name="login_method" id="login_method" value="password">
        
        <?php if ($signin_error): ?> <div class="error"><?php echo htmlspecialchars($signin_error); ?></div> <?php endif; ?>
        <?php if ($signin_success): ?> <div class="success"><?php echo htmlspecialchars($signin_success); ?></div> <?php endif; ?>
        
        <label>Email Address</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($signin_email); ?>" required>
        
        <label>Sign In Method</label>
        <div class="method-toggle">
            <div class="method-btn active" id="btn-pwd" onclick="setLoginMethod('password')">Use Password</div>
            <div class="method-btn" id="btn-magic" onclick="setLoginMethod('magic_link')">Use Google Verification</div>
        </div>

        <div class="password-field-wrapper" id="password-wrapper">
            <label>Password</label>
            <input type="password" name="password" id="signin-password" required>
        </div>
        
        <button type="submit" class="primary" id="signin-btn">Login to RoadRanger</button>
    </form>

    <form id="signup-form" class="<?php echo $active_tab === 'signup' ? 'active' : ''; ?>" method="POST" action="">
        <input type="hidden" name="form_type" value="signup">
        <?php if ($signup_error): ?> <div class="error"><?php echo htmlspecialchars($signup_error); ?></div> <?php endif; ?>

        <label>Email Address</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($signup_email); ?>" required>
        
        <label>First Name</label>
        <input type="text" name="first_name" placeholder="First Name" value="<?php echo htmlspecialchars($first_name); ?>" required>

        <label>Last Name</label>
        <input type="text" name="last_name" placeholder="Last Name" value="<?php echo htmlspecialchars($last_name); ?>" required>

        <label>Phone Number</label>
        <input type="tel" name="phone_number" placeholder="e.g., 09123456789" value="<?php echo htmlspecialchars($signup_phone); ?>" required>

        <label>Gender</label>
        <select name="gender" required>
            <option value="" disabled <?php echo $signup_gender === '' ? 'selected' : ''; ?>>Select Gender</option>
            <option value="Male" <?php echo $signup_gender === 'Male' ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?php echo $signup_gender === 'Female' ? 'selected' : ''; ?>>Female</option>
        </select>

        <label>Birthday (Used for Difficulty Setting)</label>
        <input type="date" name="birthday" value="<?php echo htmlspecialchars($birthday); ?>" required>
        
        <label>Password</label>
        <input type="password" name="password" required placeholder="Min. 8 characters">
        
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>
        
        <div class="terms-container">
            <input type="checkbox" name="terms" value="1" id="terms-box" required> 
            <label for="terms-box">I agree to the RoadRanger Terms & Conditions</label>
        </div>
        
        <button type="submit" class="primary">Create Account</button>
    </form>
</div>

<script>
    function switchTab(type) {
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        const clickedTab = window.event ? window.event.currentTarget : document.querySelector(`.tab[onclick*='${type}']`);
        if(clickedTab) clickedTab.classList.add('active');

        if (type === 'signin') {
            document.getElementById('signin-form').classList.add('active');
            document.getElementById('signup-form').classList.remove('active');
        } else {
            document.getElementById('signin-form').classList.remove('active');
            document.getElementById('signup-form').classList.add('active');
        }
    }

    function setLoginMethod(method) {
        document.getElementById('login_method').value = method;
        const pwdWrapper = document.getElementById('password-wrapper');
        const pwdInput = document.getElementById('signin-password');
        const submitBtn = document.getElementById('signin-btn');

        document.getElementById('btn-pwd').classList.remove('active');
        document.getElementById('btn-magic').classList.remove('active');

        if (method === 'password') {
            document.getElementById('btn-pwd').classList.add('active');
            pwdWrapper.style.display = 'flex';
            pwdInput.setAttribute('required', 'required');
            submitBtn.innerText = 'Login to RoadRanger';
        } else {
            document.getElementById('btn-magic').classList.add('active');
            pwdWrapper.style.display = 'none';
            pwdInput.removeAttribute('required');
            submitBtn.innerText = 'Send OTP';
        }
    }
</script>
</body>
</html>