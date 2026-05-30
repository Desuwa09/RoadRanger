<?php
    session_start();
    require_once __DIR__ . '/../db/db_con.php';

    $active_tab = 'signin';
    $signin_email = '';
    $signup_email = '';
    $first_name = '';
    $last_name = '';
    $signup_phone = '';
    $signup_gender = '';
    $birthday = '';
    $signin_error = '';
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
            $password = $_POST['password'] ?? '';

            if ($signin_email === '' || $password === '') {
                $signin_error = 'Please enter both email and password.';
            } else {
                $conn = db_connect();
                $user = find_user_by_email($conn, $signin_email);
                if (!$user || !password_verify($password, $user['password'])) {
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
                $conn = db_connect();
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
        body { font-family: sans-serif; display: flex; justify-content: center; padding-top: 50px; background: #f4f4f4; }
        .auth-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .tabs { display: flex; margin-bottom: 20px; border-bottom: 2px solid #ddd; }
        .tab { flex: 1; text-align: center; padding: 10px; cursor: pointer; font-weight: bold; }
        .tab.active { border-bottom: 3px solid #007bff; color: #007bff; }
        form { display: none; flex-direction: column; }
        form.active { display: flex; }
        input, select { margin-bottom: 15px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; background: white; }
        button { padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .error { color: red; font-size: 0.9em; margin-bottom: 10px; }
        label { font-size: 0.9em; margin-bottom: 5px; font-weight: 600; color: #333; }
        .terms-container { display: flex; align-items: center; gap: 8px; margin-bottom: 15px; }
        .terms-container input { margin-bottom: 0; }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="tabs">
        <div class="tab <?php echo $active_tab === 'signin' ? 'active' : ''; ?>" onclick="switchTab('signin')">Sign In</div>
        <div class="tab <?php echo $active_tab === 'signup' ? 'active' : ''; ?>" onclick="switchTab('signup')">Sign Up</div>
    </div>

    <form id="signin-form" class="<?php echo $active_tab === 'signin' ? 'active' : ''; ?>" method="POST" action="">
        <input type="hidden" name="form_type" value="signin">
        <?php if ($signin_error): ?> <div class="error"><?php echo $signin_error; ?></div> <?php endif; ?>
        
        <label>Email Address</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($signin_email); ?>" required>
        
        <label>Password</label>
        <input type="password" name="password" required>
        
        <button type="submit">Login to RoadRangers</button>
    </form>

    <form id="signup-form" class="<?php echo $active_tab === 'signup' ? 'active' : ''; ?>" method="POST" action="">
        <input type="hidden" name="form_type" value="signup">
        <?php if ($signup_error): ?> <div class="error"><?php echo $signup_error; ?></div> <?php endif; ?>

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
            <label for="terms-box" style="margin-bottom:0; font-weight:normal;">I agree to the TMU Road Safety Terms</label>
        </div>
        
        <button type="submit">Create Account</button>
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
</script>

</body>
</html>