<?php
define('ALLOWED_ACCESS', true);
require_once '../includes/session.php';
require_once '../includes/config.cap.php';
require_once '../includes/srp6.php';
require_once '../includes/config.mail.php';
require_once '../languages/language.php';
$page_class = 'reset_password';
require_once '../includes/header.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ".SUBDIR."account");
    exit();
}

$errors = [];
$success = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$valid_token = false;
$email = '';
$username = '';

// Generate nonce for form submission
if (!isset($_SESSION['reset_nonce'])) {
    $_SESSION['reset_nonce'] = bin2hex(random_bytes(16));
}
$nonce = $_SESSION['reset_nonce'];

// Function to send confirmation email
function sendResetConfirmationEmail($username, $email) {
    global $errors;
    try {
        $mail = getMailer();
        $mail->addAddress($email, $username);
        $mail->AddEmbeddedImage('logo.png', 'logo_cid');
        $mail->Subject = translate('email_subject_confirmation', 'Password Reset Confirmation');
        $mail->Body = "<h2>" . str_replace('{username}', htmlspecialchars($username), translate('email_greeting', 'Welcome, {username}!')) . "</h2>
            <img src='cid:logo_cid' alt='Sahtout logo'>
            <p>" . translate('email_success', 'Your password has been successfully reset.') . "</p>
            <p>" . translate('email_contact_support', 'If you did not perform this action, please contact support immediately.') . "</p>";
        if (!$mail->send()) {
            $errors[] = translate('error_email_failed', 'Failed to send confirmation email: ') . $mail->ErrorInfo;
        }
    } catch (Exception $e) {
        $errors[] = translate('error_email_failed', 'Email error: ') . $e->getMessage();
    }
}

if ($token) {
    // Delete expired or used tokens
    $stmt_delete = $site_db->prepare("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
    $stmt_delete->execute();
    $stmt_delete->close();

    // Validate token
    $stmt = $site_db->prepare("SELECT email FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $email = $row['email'];

        // Check account table first
        $stmt2 = $auth_db->prepare("SELECT username FROM account WHERE LOWER(email) = LOWER(?)");
        $stmt2->bind_param('s', $email);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        if ($result2->num_rows > 0) {
            $username = $result2->fetch_assoc()['username'];
            $valid_token = true;
        } else {
            // Check pending_accounts table
            $stmt3 = $site_db->prepare("SELECT username FROM pending_accounts WHERE LOWER(email) = LOWER(?)");
            $stmt3->bind_param('s', $email);
            $stmt3->execute();
            $result3 = $stmt3->get_result();
            if ($result3->num_rows > 0) {
                $errors[] = translate('error_account_not_active', 'Your account is not active yet. Please activate your account to reset your password.');
            } else {
                $errors[] = translate('error_account_not_exist', 'Account does not exist.');
            }
            $stmt3->close();
        }
        $stmt2->close();
    } else {
        $errors[] = translate('error_token_invalid', 'The reset link is invalid or has expired.');
    }
    $stmt->close();
} else {
    $errors[] = translate('error_no_token', 'No reset token provided.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    // Validate nonce to prevent re-submission
    $submitted_nonce = $_POST['nonce'] ?? '';
    if ($submitted_nonce !== $_SESSION['reset_nonce']) {
        $errors[] = translate('error_invalid_nonce', 'Invalid or expired form submission. Please try again.');
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate password
        if (empty($password)) {
            $errors[] = translate('error_password_required', 'Password is required.');
        } elseif (strlen($password) < 8) {
            $errors[] = translate('error_password_short', 'Password must be at least 8 characters long.');
        } elseif ($password !== $confirm_password) {
            $errors[] = translate('error_password_mismatch', 'Passwords do not match.');
        }

        // Google reCAPTCHA validation
        if (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED) {
            $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
            $verify = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . RECAPTCHA_SECRET_KEY . '&response=' . $recaptchaResponse);
            $responseData = json_decode($verify);
            if (!$responseData->success) {
                $errors[] = translate('error_recaptcha_failed', 'reCAPTCHA verification failed.');
            }
        }

        if (empty($errors)) {
            // Generate new SRP-6a salt and verifier
            $salt = SRP6::GenerateSalt();
            $verifier = SRP6::calculateVerifier($username, $password, $salt);

            // Update account table
            $stmt = $auth_db->prepare("UPDATE account SET salt = ?, verifier = ? WHERE email = ?");
            $stmt->bind_param('sss', $salt, $verifier, $email);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Delete token from password_resets
                $stmt2 = $site_db->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt2->bind_param('s', $token);
                $stmt2->execute();
                $stmt2->close();
                // Send confirmation email only if SMTP is enabled
                if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
                    sendResetConfirmationEmail($username, $email);
                }
                $success = translate('success_password_reset', 'Your password has been successfully reset. You can now log in.');
                // Clear nonce and token to prevent re-submission
                unset($_SESSION['reset_nonce']);
                $token = '';
                $valid_token = false;
            } else {
                $errors[] = translate('error_password_update_failed', 'Failed to update password.');
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_SESSION['lang'] ?? 'en'); ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="description" content="<?php echo translate('meta_description', 'Reset your password for our World of Warcraft server.'); ?>">
    <title><?php echo translate('page_title', 'Reset Password'); ?></title>
    <style>
        body.reset_password {
            color: #fff;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: url('<?php echo SUBDIR ?>img/backgrounds/bg-login.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'UnifrakturCook', 'Arial', sans-serif;
            position: relative;
        }
        html, body {
            width: 100%;
            overflow-x: hidden;
            margin: 0;
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
            max-width: 500px;
            width: calc(100% - 2rem);
            background: rgba(0, 0, 0, 0.7);
            border: 2px solid #ffd700;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
            padding: 2.5rem;
        }
        .form-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-section h2 {
            font-size: 2.8rem;
            font-family: 'UnifrakturCook', sans-serif;
            color: #ffd700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
            margin-bottom: 1.2rem;
            text-align: center;
        }
        .form-section form {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }
        .form-section input {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            font-family: 'Arial', sans-serif;
            background: #333;
            color: #fff;
            border: 1px solid #ffd700;
            border-radius: 4px;
            outline: none;
            transition: border-color 0.3s ease;
            margin-bottom: 0.5rem;
        }
        .form-section input:focus {
            border-color: #ffe600;
            box-shadow: 0 0 5px rgba(255, 230, 0, 0.5);
        }
        .form-section input::placeholder {
            color: #ccc;
        }
        .g-recaptcha {
            margin: 0 auto 0.5rem;
            display: flex;
            justify-content: center;
        }
        .form-section button {
            background: #333;
            color: #ffd700;
            border: 2px solid #ffd700;
            padding: 1rem 2rem;
            font-family: 'UnifrakturCook', sans-serif;
            font-size: 1.2rem;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .form-section button:hover {
            background: #ffd700;
            color: #000;
            transform: scale(1.05);
        }
        .form-section .error {
            color: #ff0000;
            font-size: 0.9rem;
            text-align: center;
            margin: 0.5rem 0 0;
        }
        .form-section .success {
            color: #00ff00;
            font-size: 0.9rem;
            text-align: center;
            margin: 0.5rem 0 0;
        }
        .form-section .login-link {
            text-align: center;
            font-size: 1.1rem;
            font-family: 'UnifrakturCook', sans-serif;
            color: #fff;
            margin-top: 1.5rem;
        }
        .form-section .login-link a {
            color: #ffd700;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .form-section .login-link a:hover {
            color: #ffe600;
            text-decoration: underline;
        }
        footer {
            flex-shrink: 0;
            width: 100%;
            text-align: center;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.7);
            border-top: 2px solid #ffd700;
            color: #fff;
            font-family: 'Arial', sans-serif;
            font-size: 0.9rem;
            position: relative;
            z-index: 2;
        }
        @media (max-width: 767px) {
            .wrapper {
                padding: 0;
                margin-top: 100px;
            }
            .form-container {
                max-width: 90%;
                padding: 1.5rem;
            }
            .form-section h2 {
                font-size: 2.2rem;
            }
            .form-section input {
                font-size: 1rem;
                padding: 0.8rem;
            }
            .form-section button {
                font-size: 1.1rem;
                padding: 0.8rem 1.5rem;
            }
            .form-section .login-link {
                font-size: 1rem;
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body class="reset_password">
    <div class="wrapper">
        <div class="form-container">
            <div class="form-section">
                <h2><?php echo translate('reset_title', 'Reset Password'); ?></h2>
                <?php if (!empty($errors)): ?>
                    <div class="error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success">
                        <p><?php echo htmlspecialchars($success); ?></p>
                        <button onclick="window.location.href='<?php echo SUBDIR ?>login'" class="login-button"><?php echo translate('login_link', 'Back to Login'); ?></button>
                    </div>
                    <script>
                        setTimeout(() => {
                            window.location.href = '<?php echo SUBDIR ?>login';
                        }, 3000);
                    </script>
                <?php else: ?>
                    <?php if ($valid_token): ?>
                        <form method="POST">
                            <input type="hidden" name="nonce" value="<?php echo htmlspecialchars($nonce); ?>">
                            <input type="password" name="password" placeholder="<?php echo translate('password_placeholder', 'New Password'); ?>" required>
                            <input type="password" name="confirm_password" placeholder="<?php echo translate('confirm_password_placeholder', 'Confirm Password'); ?>" required>
                            <?php if (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED): ?>
                                <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                            <?php endif; ?>
                            <button type="submit"><?php echo translate('reset_button', 'Reset Password'); ?></button>
                            <div class="login-link">
                                <a href="<?php echo SUBDIR ?>login"><?php echo translate('login_link', 'Back to Login'); ?></a>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>