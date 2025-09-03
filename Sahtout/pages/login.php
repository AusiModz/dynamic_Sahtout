<?php
define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
require_once '../languages/language.php';
require_once '../includes/config.cap.php';
require_once '../includes/srp6.php';

// Brute force prevention settings
define('MAX_LOGIN_ATTEMPTS', 5); // Maximum allowed attempts
define('LOCKOUT_DURATION', 900); // 15 minutes in seconds
define('ATTEMPT_WINDOW', 3600); // 1 hour window for attempt counting

// Redirect to account if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: /Sahtout/account");
    exit();
}

$page_class = 'login';

$errors = [];
$username = '';
$show_resend_button = false;
$remaining_attempts = MAX_LOGIN_ATTEMPTS; // Default to max attempts

// Function to get client IP address
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Function to get current attempt count
function getAttemptCount($site_db, $ip_address, $username) {
    $stmt = $site_db->prepare("SELECT attempts, last_attempt 
        FROM failed_logins 
        WHERE ip_address = ? AND username = ?");
    $upper_username = strtoupper($username);
    $stmt->bind_param('ss', $ip_address, $upper_username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && (int)$row['last_attempt'] >= time() - ATTEMPT_WINDOW) {
        return $row['attempts'];
    }
    return 0;
}

// Function to check and update login attempts
function checkBruteForce($site_db, $ip_address, $username) {
    global $errors, $remaining_attempts;
    
    // Check if IP and username combo exists in failed_logins
    $stmt = $site_db->prepare("SELECT attempts, last_attempt, block_until 
        FROM failed_logins 
        WHERE ip_address = ? AND username = ?");
    $upper_username = strtoupper($username);
    $stmt->bind_param('ss', $ip_address, $upper_username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    // Delete expired block records
    if ($row && $row['block_until'] && (int)$row['block_until'] <= time()) {
        $stmt = $site_db->prepare("DELETE FROM failed_logins 
            WHERE ip_address = ? AND username = ? AND block_until <= ?");
        $current_time = time();
        $stmt->bind_param('ssi', $ip_address, $upper_username, $current_time);
        $stmt->execute();
        $stmt->close();
        $row = null; // Reset row as it no longer exists
    }
    
    // Reset attempts if outside the attempt window
    if ($row && (int)$row['last_attempt'] < time() - ATTEMPT_WINDOW) {
        $stmt = $site_db->prepare("UPDATE failed_logins 
            SET attempts = 0, block_until = NULL 
            WHERE ip_address = ? AND username = ?");
        $stmt->bind_param('ss', $ip_address, $upper_username);
        $stmt->execute();
        $stmt->close();
        $row['attempts'] = 0;
        $row['block_until'] = null;
    }
    
    // Update remaining attempts
    $remaining_attempts = MAX_LOGIN_ATTEMPTS - ($row['attempts'] ?? 0);
    
    // Check if currently blocked
    if ($row && $row['block_until'] && (int)$row['block_until'] > time()) {
        $remaining_time = ceil(((int)$row['block_until'] - time()) / 60);
        $errors[] = translate('error_too_many_attempts', 'Too many login attempts (%d made). Please try again in %d minutes.', $row['attempts'], $remaining_time);
        return false;
    }
    
    // Check if max attempts reached
    if ($row && $row['attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $block_until = time() + LOCKOUT_DURATION;
        $stmt = $site_db->prepare("UPDATE failed_logins 
            SET block_until = ? 
            WHERE ip_address = ? AND username = ?");
        $stmt->bind_param('iss', $block_until, $ip_address, $upper_username);
        $stmt->execute();
        $stmt->close();
        
        $remaining_time = ceil(LOCKOUT_DURATION / 60);
        $errors[] = translate('error_too_many_attempts', 'Too many login attempts (%d made). Please try again in %d minutes.', $row['attempts'], $remaining_time);
        return false;
    }
    
    return true;
}

// Function to log failed login attempt
function logFailedAttempt($site_db, $ip_address, $username) {
    $upper_username = strtoupper($username);
    // Check if IP and username combo exists
    $stmt = $site_db->prepare("SELECT id FROM failed_logins WHERE ip_address = ? AND username = ?");
    $stmt->bind_param('ss', $ip_address, $upper_username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing record
        $stmt = $site_db->prepare("UPDATE failed_logins 
            SET attempts = attempts + 1, last_attempt = UNIX_TIMESTAMP() 
            WHERE ip_address = ? AND username = ?");
        $stmt->bind_param('ss', $ip_address, $upper_username);
        $stmt->execute();
    } else {
        // Insert new record
        $stmt = $site_db->prepare("INSERT INTO failed_logins (ip_address, username, attempts, last_attempt) 
            VALUES (?, ?, 1, UNIX_TIMESTAMP())");
        $stmt->bind_param('ss', $ip_address, $upper_username);
        $stmt->execute();
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip_address = getUserIP();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check for brute force attempts
    if (!checkBruteForce($site_db, $ip_address, $username)) {
        // Skip further processing if locked out
    } else {
        // Basic field validation
        if (empty($username)) {
            $errors[] = translate('error_username_required', 'Username is required');
        }
        if (empty($password)) {
            $errors[] = translate('error_password_required', 'Password is required');
        }

        // Google reCAPTCHA validation (always required)
        if (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED) {
            $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
            if (empty($recaptchaResponse)) {
                $errors[] = translate('error_recaptcha_failed', 'reCAPTCHA verification failed.');
                // Do not log failed attempt here as account existence is not yet verified
            } else {
                $verify = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . RECAPTCHA_SECRET_KEY . '&response=' . $recaptchaResponse);
                $responseData = json_decode($verify);
                if (!$responseData->success) {
                    $errors[] = translate('error_recaptcha_failed', 'reCAPTCHA verification failed.');
                    // Do not log failed attempt here as account existence is not yet verified
                }
            }
        }

        if (empty($errors)) {
            // Check if account is in pending_accounts and not activated
            $stmt = $site_db->prepare("SELECT username FROM pending_accounts WHERE username = ? AND activated = 0");
            $upper_username = strtoupper($username);
            $stmt->bind_param('s', $upper_username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = translate('error_account_not_activated', 'Your account is not activated. Please check your email to activate your account.');
                $show_resend_button = true;
                logFailedAttempt($site_db, $ip_address, $username);
            }
            $stmt->close();

            // Proceed with login if no errors
            if (empty($errors)) {
                if ($auth_db->connect_error) {
                    die("Connection failed: " . $auth_db->connect_error);
                }

                $stmt = $auth_db->prepare("SELECT id, username, salt, verifier FROM account WHERE username = ?");
                $stmt->bind_param('s', $upper_username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $errors[] = translate('error_invalid_credentials', 'Invalid username or password');
                    // Do not log failed attempt for non-existent account
                } else {
                    $account = $result->fetch_assoc();

                    if (SRP6::VerifyPassword($username, $password, $account['salt'], $account['verifier'])) {
                        $_SESSION['user_id'] = $account['id'];
                        $_SESSION['username'] = $account['username'];

                        $update = $auth_db->prepare("UPDATE account SET last_login = NOW() WHERE id = ?");
                        $update->bind_param('i', $account['id']);
                        $update->execute();
                        $update->close();

                        // Clear failed attempts on successful login
                        $stmt = $site_db->prepare("DELETE FROM failed_logins WHERE ip_address = ? AND username = ?");
                        $stmt->bind_param('ss', $ip_address, $upper_username);
                        $stmt->execute();
                        $stmt->close();

                        header("Location: /Sahtout/account");
                        exit();
                    } else {
                        $errors[] = translate('error_invalid_credentials', 'Invalid username or password');
                        logFailedAttempt($site_db, $ip_address, $username);
                    }
                }

                $stmt->close();
                $auth_db->close();
            }
        }
    }
    // Update remaining attempts after processing
    $remaining_attempts = MAX_LOGIN_ATTEMPTS - getAttemptCount($site_db, $ip_address, $username);
}

// Get remaining attempts for display (even on GET request)
if (!empty($username)) {
    $remaining_attempts = MAX_LOGIN_ATTEMPTS - getAttemptCount($site_db, getUserIP(), $username);
}

// Include header after processing form
include_once '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_SESSION['lang'] ?? 'en'); ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="description" content="<?php echo translate('meta_description', 'Log in to your account to join our World of Warcraft server adventure!'); ?>">
    <title><?php echo translate('page_title', 'Login'); ?></title>
    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            overflow-x: hidden;
            margin: 0;
            background: url('/sahtout/img/backgrounds/bg-login.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'UnifrakturCook', 'Arial', sans-serif;
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        body::before {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1;
        }

        .wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            width: 100%;
            position: relative;
            z-index: 2;
        }

        .form-container {
            max-width: 480px;
            width: calc(100% - 2rem);
            background: #1a1a1a88;
            border: 3px solid #f1c40f;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(241, 196, 15, 0.4), 0 0 40px rgba(0, 0, 0, 0.8);
            padding: 2rem;
            animation: pulse 3s infinite ease-in-out;
            transition: transform 0.3s ease;
        }

        .form-container:hover {
            transform: translateY(-5px) rotate(1deg);
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 8px 24px rgba(241, 196, 15, 0.4), 0 0 40px rgba(0, 0, 0, 0.8); }
            50% { box-shadow: 0 8px 32px rgba(241, 196, 15, 0.6), 0 0 48px rgba(0, 0, 0, 0.9); }
        }

        .form-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-section h2 {
            font-size: 3rem;
            font-family: 'UnifrakturCook', sans-serif;
            color: #f1c40f;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.9);
            margin-bottom: 1.5rem;
            text-align: center;
            letter-spacing: 1px;
        }

        .form-section form {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .form-section input {
            width: 100%;
            padding: 0.9rem;
            font-size: 1.1rem;
            font-family: 'Arial', sans-serif;
            background: #2c2c2c;
            color: #fff;
            border: 2px solid #f1c40f;
            border-radius: 6px;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-section input:focus {
            border-color: #ffe600;
            box-shadow: 0 0 8px rgba(255, 230, 0, 0.6);
        }

        .form-section input::placeholder {
            color: #999;
            font-size: 1rem;
        }

        .g-recaptcha {
            margin: 1.2rem auto;
            display: flex;
            justify-content: center;
        }

        .form-section button {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: #fff;
            border: 2px solid #f1c40f;
            padding: 0.9rem 1.8rem;
            font-size: 1.3rem;
            border-radius: 6px;
            cursor: url('/Sahtout/img/hover_wow.gif') 16 16, auto;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-section button:hover {
            background: linear-gradient(135deg, #0e65d6ff 0%, #26a938ff 100%);
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(230, 247, 3, 0.6);
        }

        .form-section .error {
            color: #e74c3c;
            font-size: 1.1rem;
            font-family: 'Arial', sans-serif;
            text-align: center;
            margin: 0.6rem 0 0;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7);
        }

        .form-section .attempts-info {
            color: #f1c40f;
            font-size: 1.1rem;
            font-family: 'Arial', sans-serif;
            text-align: center;
            margin: 0.6rem 0;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7);
        }

        .form-section .resend-link {
            text-align: center;
            font-size: 1.1rem;
            font-family: 'UnifrakturCook', sans-serif;
            color: #fff;
            margin-bottom: 1.2rem;
        }

        .form-section .resend-link a {
            color: #f1c40f;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .form-section .resend-link a:hover {
            color: #ffe600;
            text-decoration: underline;
        }

        .form-section .resend-link p {
            font-family: 'Courier New', Courier, monospace;
            display: inline;
            margin-right: 0.6rem;
            color: #fff;
        }

        .form-section .register-link, .form-section .forgot-password-link {
            text-align: center;
            font-size: 1.1rem;
            font-family: 'UnifrakturCook', sans-serif;
            color: #fff;
            margin-top: 1.2rem;
        }

        .form-section .register-link a, .form-section .forgot-password-link a {
            color: #f1c40f;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .form-section .register-link a:hover, .form-section .forgot-password-link a:hover {
            color: #ffe600;
            text-decoration: underline;
        }

        @media (max-width: 767px) {
            html, body {
                width: 100%;
                overflow-x: hidden;
            }

            .wrapper {
                padding: 0;
                margin-top: 80px;
            }

            .form-container {
                max-width: 100%;
                width: calc(100% - 1.5rem);
                padding: 1.5rem;
                margin: 1.5rem auto;
                box-shadow: 0 6px 16px rgba(241, 196, 15, 0.3);
            }

            .form-container:hover {
                transform: translateY(-3px) rotate(0.5deg);
            }

            .form-section h2 {
                font-size: 2.4rem;
            }

            .form-section input {
                font-size: 1rem;
                padding: 0.8rem;
            }

            .form-section button {
                font-size: 1.2rem;
                padding: 0.8rem 1.5rem;
            }

            .g-recaptcha {
                transform: scale(0.85);
                transform-origin: center;
            }

            .form-section .resend-link, .form-section .register-link, .form-section .forgot-password-link, .form-section .attempts-info {
                font-size: 1rem;
                margin-top: 1rem;
            }

            .form-section .error {
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .form-section h2 {
                font-size: 2rem;
            }

            .form-section input {
                font-size: 0.95rem;
                padding: 0.7rem;
            }

            .form-section button {
                font-size: 1.1rem;
                padding: 0.7rem 1.2rem;
            }

            .g-recaptcha {
                transform: scale(0.77);
            }

            .form-section .resend-link, .form-section .register-link, .form-section .forgot-password-link, .form-section .attempts-info {
                font-size: 0.95rem;
            }

            .form-section .error {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="form-container">
        <div class="form-section">
            <h2><?php echo translate('login_title', 'Login'); ?></h2>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                    <?php if ($show_resend_button): ?>
                        <div class="resend-link">
                            <p><?php echo translate('resend_activation_prompt', 'CLICK here:'); ?></p>
                            <a href="/sahtout/resend_activation?username=<?php echo htmlspecialchars($username); ?>">
                                <?php echo translate('resend_activation_link', 'Resend Activation Code'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($remaining_attempts < MAX_LOGIN_ATTEMPTS && $remaining_attempts > 0): ?>
                <div class="attempts-info">
                    <p><?php echo translate('remaining_attempts', 'You have %d login attempts remaining.', $remaining_attempts); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="text" name="username" placeholder="<?php echo translate('username_placeholder', 'Username'); ?>" required value="<?php echo htmlspecialchars($username); ?>">
                <br>
                <input type="password" name="password" placeholder="<?php echo translate('password_placeholder', 'Password'); ?>" required>
                <?php if (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED): ?>
                    <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                <?php endif; ?>
                <button type="submit"><?php echo translate('login_button', 'Sign In'); ?></button>
                <div class="register-link">
                    <?php echo translate('register_link_text', 'Don\'t have an account? <a href="/sahtout/register">Register now</a>'); ?>
                </div>
                <div class="forgot-password-link">
                    <?php echo translate('forgot_password_link_text', 'Forgot your password? <a href="/sahtout/forgot_password">Reset it here</a>'); ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
</body>
</html>

<?php include_once '../includes/footer.php'; ?>