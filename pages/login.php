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
    $verify_email = $_SESSION['pending_verification_email'] ?? '';
    $verify_error = '';
    $verify_success = '';

    // If we already have a pending signup waiting on a code (e.g. the user
    // refreshed the page instead of losing their place), resume that step.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $verify_email !== '') {
        $active_tab = 'verify';
    }

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

    /**
     * Builds a PHPMailer instance pre-configured with the RoadRangers SMTP
     * settings. Shared by the magic-link sign-in and the signup code email.
     */
    function build_mailer() {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'tkurumi965@gmail.com';
        $mail->Password   = 'xzli arev bdkm zfwt';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('your-roadranger-email@gmail.com', 'RoadRangers Platform');
        return $mail;
    }

    /** Generates a zero-padded 6-digit numeric code, e.g. "042917". */
    function generate_otp_code() {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Emails the 6-digit signup verification code. Returns true on success,
     * throws PHPMailer\Exception on failure.
     */
    function send_signup_code_email($email, $first_name, $code) {
        $mail = build_mailer();
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Your RoadRangers verification code';
        $mail->Body    = "Hello " . htmlspecialchars($first_name) . ",<br><br>
                          Thanks for signing up for RoadRangers! Enter this code to finish creating your account. It expires in 10 minutes:<br><br>
                          <div style='font-family: monospace; font-size: 32px; font-weight: bold; letter-spacing: 8px; background-color: #FFC53D; color: #1A1A1A; padding: 14px 20px; border-radius: 6px; display: inline-block;'>{$code}</div><br><br>
                          If you didn't try to create a RoadRangers account, you can safely ignore this email.";

        return $mail->send();
    }

    /** Fetches a pending_signups row by email, or null if none exists. */
    function find_pending_signup($conn, $email) {
        $stmt = $conn->prepare("SELECT * FROM pending_signups WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form_type = $_POST['form_type'] ?? 'signin';

        // ------------------------------------------------------------------
        // SIGN IN
        // ------------------------------------------------------------------
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
                                $mail = build_mailer();
                                $mail->addAddress($user['email']);

                                $magicLink = "http://" . $_SERVER['HTTP_HOST'] . "/roadranger(2)/pages/verify.php?token=" . $token;

                                $mail->isHTML(true);
                                $mail->Subject = 'Your Secure Access Link to RoadRangers';
                                $mail->Body    = "Hello " . htmlspecialchars($user['first_name']) . ",<br><br>
                                                  Click the link below to verify your identity and log into your dashboard. This link expires in 15 minutes:<br><br>
                                                  <a href='{$magicLink}' style='padding: 10px 15px; background-color: #FFC53D; color: #1A1A1A; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Login to RoadRangers</a><br><br>
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
        }

        // ------------------------------------------------------------------
        // SIGN UP — validate and stage the account, but do NOT create it yet
        // ------------------------------------------------------------------
        else if ($form_type === 'signup') {
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
            } elseif (find_user_by_email($conn, $signup_email)) {
                $signup_error = 'An account with that email already exists.';
            } else {
                $code = generate_otp_code();
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                try {
                    // Clear out any earlier abandoned attempt for this email,
                    // then stage the new one. Nothing touches `users` yet.
                    $conn->prepare("DELETE FROM pending_signups WHERE email = :email")
                         ->execute([':email' => $signup_email]);

                    $insert = $conn->prepare(
                        "INSERT INTO pending_signups
                            (email, first_name, last_name, phone_number, gender, birthday, password, otp_code, otp_expires_at, created_at)
                         VALUES
                            (:email, :first_name, :last_name, :phone_number, :gender, :birthday, :password, :otp_code, :otp_expires_at, NOW())"
                    );
                    $insert->execute([
                        ':email'          => $signup_email,
                        ':first_name'     => $first_name,
                        ':last_name'      => $last_name,
                        ':phone_number'   => $signup_phone,
                        ':gender'         => $signup_gender,
                        ':birthday'       => $birthday,
                        ':password'       => $password,
                        ':otp_code'       => $code,
                        ':otp_expires_at' => $expires,
                    ]);

                    send_signup_code_email($signup_email, $first_name, $code);

                    $_SESSION['pending_verification_email'] = $signup_email;
                    $verify_email = $signup_email;
                    $active_tab = 'verify';
                    $verify_success = 'We sent a 6-digit code to ' . htmlspecialchars($signup_email) . '. Enter it below to finish creating your account.';
                } catch (Exception $e) {
                    $signup_error = "We couldn't send your verification code. Error: {$e->getMessage()}";
                }
            }
        }

        // ------------------------------------------------------------------
        // VERIFY CODE — this is the only path that actually creates the user
        // ------------------------------------------------------------------
        else if ($form_type === 'verify_code') {
            $active_tab = 'verify';
            $verify_email = trim($_POST['email'] ?? $verify_email);
            $submitted_code = trim($_POST['code'] ?? '');

            $pending = find_pending_signup($conn, $verify_email);

            if (!$pending) {
                $verify_error = "We couldn't find a pending signup for that email. Please sign up again.";
                $active_tab = 'signup';
                unset($_SESSION['pending_verification_email']);
                $verify_email = '';
            } elseif ($submitted_code === '') {
                $verify_error = 'Please enter the code we emailed you.';
            } elseif (strtotime($pending['otp_expires_at']) < time()) {
                $verify_error = 'That code has expired. Request a new one below.';
            } elseif (!hash_equals($pending['otp_code'], $submitted_code)) {
                $verify_error = 'Incorrect code. Please try again.';
            } else {
                $tier = determine_user_tier($pending['birthday']);

                $user = create_user(
                    $conn,
                    $pending['email'],
                    $pending['first_name'],
                    $pending['last_name'],
                    $pending['password'],
                    $pending['birthday'],
                    $tier['age_group'],
                    $tier['difficulty'],
                    $pending['phone_number'],
                    $pending['gender']
                );

                if ($user !== false) {
                    $conn->prepare("DELETE FROM pending_signups WHERE email = :email")
                         ->execute([':email' => $pending['email']]);
                    unset($_SESSION['pending_verification_email']);

                    login_user($user);
                    header('Location: users/dashboard.php');
                    exit;
                } else {
                    $verify_error = 'Your code was correct, but we could not create your account. Please try signing up again.';
                }
            }
        }

        // ------------------------------------------------------------------
        // RESEND CODE
        // ------------------------------------------------------------------
        else if ($form_type === 'resend_code') {
            $active_tab = 'verify';
            $verify_email = trim($_POST['email'] ?? $verify_email);

            $pending = find_pending_signup($conn, $verify_email);

            if (!$pending) {
                $verify_error = "We couldn't find a pending signup for that email. Please sign up again.";
                $active_tab = 'signup';
                unset($_SESSION['pending_verification_email']);
                $verify_email = '';
            } else {
                $code = generate_otp_code();
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                try {
                    $conn->prepare("UPDATE pending_signups SET otp_code = :code, otp_expires_at = :expires WHERE email = :email")
                         ->execute([':code' => $code, ':expires' => $expires, ':email' => $verify_email]);

                    send_signup_code_email($verify_email, $pending['first_name'], $code);
                    $verify_success = 'A new code is on its way to ' . htmlspecialchars($verify_email) . '.';
                } catch (Exception $e) {
                    $verify_error = "We couldn't resend your code. Error: {$e->getMessage()}";
                }
            }
        }

        // ------------------------------------------------------------------
        // CANCEL VERIFICATION — abandon this signup and go back to the form
        // ------------------------------------------------------------------
        else if ($form_type === 'cancel_verification') {
            $email_to_drop = trim($_POST['email'] ?? $verify_email);
            $conn->prepare("DELETE FROM pending_signups WHERE email = :email")
                 ->execute([':email' => $email_to_drop]);
            unset($_SESSION['pending_verification_email']);
            $verify_email = '';
            $active_tab = 'signup';
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login-SignUp | RoadRangers</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/roadranger-theme.css">
</head>
<body>

<div class="auth-container">
    <div class="hazard-strip" aria-hidden="true"></div>

    <div class="header">
        <span class="header-badge">RR &middot; ROUTE ACCESS</span>
        <h1>RoadRanger</h1>
        <p><?php echo $active_tab === 'verify' ? 'One more step to hit the road' : 'Sign in and keep your convoy moving'; ?></p>
    </div>

    <?php if ($active_tab !== 'verify'): ?>
    <div class="tabs">
        <div class="tab <?php echo $active_tab === 'signin' ? 'active' : ''; ?>" onclick="switchTab('signin')">Sign In</div>
        <div class="tab <?php echo $active_tab === 'signup' ? 'active' : ''; ?>" onclick="switchTab('signup')">Sign Up</div>
    </div>
    <?php endif; ?>

    <form id="signin-form" class="<?php echo $active_tab === 'signin' ? 'active' : ''; ?>" method="POST" action="">
        <input type="hidden" name="form_type" value="signin">
        <input type="hidden" name="login_method" id="login_method" value="password">

        <?php if ($signin_error): ?> <div class="alert alert-error"><?php echo htmlspecialchars($signin_error); ?></div> <?php endif; ?>
        <?php if ($signin_success): ?> <div class="alert alert-success"><?php echo $signin_success; ?></div> <?php endif; ?>

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
        <?php if ($signup_error): ?> <div class="alert alert-error"><?php echo htmlspecialchars($signup_error); ?></div> <?php endif; ?>

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
            <label for="terms-box">I agree to the RoadRanger Terms &amp; Conditions</label>
        </div>

        <button type="submit" class="primary">Create Account</button>
    </form>

    <?php if ($active_tab === 'verify'): ?>
    <form id="verify-form" class="active" method="POST" action="">
        <input type="hidden" name="form_type" value="verify_code">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($verify_email); ?>">

        <?php if ($verify_error): ?> <div class="alert alert-error"><?php echo htmlspecialchars($verify_error); ?></div> <?php endif; ?>
        <?php if ($verify_success): ?> <div class="alert alert-success"><?php echo $verify_success; ?></div> <?php endif; ?>

        <label>Verification Code</label>
        <input type="text" name="code" class="otp-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" autocomplete="one-time-code" required>

        <button type="submit" class="primary">Verify &amp; Create Account</button>
    </form>

    <div class="verify-footer">
        <form method="POST" action="" class="inline-form">
            <input type="hidden" name="form_type" value="resend_code">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($verify_email); ?>">
            <button type="submit" class="link-btn">Resend code</button>
        </form>
        <span class="verify-footer-divider">&middot;</span>
        <form method="POST" action="" class="inline-form">
            <input type="hidden" name="form_type" value="cancel_verification">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($verify_email); ?>">
            <button type="submit" class="link-btn">Use a different email</button>
        </form>
    </div>
    <?php endif; ?>
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