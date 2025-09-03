<?php
define('ALLOWED_ACCESS', true);
require_once 'C:\xampp\htdocs\Sahtout\includes\session.php';
require_once 'C:\xampp\htdocs\Sahtout\languages\language.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header('Location: /Sahtout/login');
    exit;
}

$page_class = 'smtp';
require_once 'C:\xampp\htdocs\Sahtout\includes\header.php';

$errors = [];
$success = false;
$configMailFile = realpath('C:\xampp\htdocs\Sahtout\includes\config.mail.php');

// Load current SMTP settings
$smtp_status = 'disabled';
if (file_exists($configMailFile)) {
    include $configMailFile;
    $smtp_status = defined('SMTP_ENABLED') && SMTP_ENABLED ? 'enabled' : 'disabled';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smtp_enabled = isset($_POST['smtp_enabled']);
    $smtpHost = trim($_POST['smtp_host'] ?? '');
    $smtpUser = trim($_POST['smtp_user'] ?? '');
    $smtpPass = trim($_POST['smtp_pass'] ?? '');
    $smtpFrom = trim($_POST['smtp_from'] ?? 'noreply@yourdomain.com');
    $smtpName = trim($_POST['smtp_name'] ?? 'Sahtout Account');
    $smtpPort = trim($_POST['smtp_port'] ?? '587');
    $smtpSecure = trim($_POST['smtp_secure'] ?? 'tls');

    // Validation only when SMTP is enabled
    if ($smtp_enabled) {
        if (empty($smtpHost)) {
            $errors[] = translate('err_smtp_host_required', 'SMTP Host is required.');
        }
        if (empty($smtpUser)) {
            $errors[] = translate('err_smtp_user_required', 'SMTP Username is required.');
        }
        if (empty($smtpPass)) {
            $errors[] = translate('err_smtp_pass_required', 'SMTP Password is required.');
        }
    }

    // Test SMTP only if enabled and no validation errors
    if (empty($errors) && $smtp_enabled) {
        require_once 'C:\xampp\htdocs\Sahtout\vendor\autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->CharSet = 'UTF-8'; // Ensure UTF-8 encoding for non-Latin characters
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = $smtpSecure;
            $mail->Port = $smtpPort;
            $mail->setFrom($smtpFrom, $smtpName);
            $mail->addAddress($smtpUser);
            $mail->Subject = translate('mail_test_subject', 'Test Email - Sahtout CMS');
            $mail->Body = translate('mail_test_body', 'This is a test email from your Sahtout CMS admin settings.');
            $mail->send();
        } catch (Exception $e) {
            $errors[] = translate('err_smtp_test_failed', 'SMTP test failed:') . ' ' . $mail->ErrorInfo;
        }
    }

    // Save settings regardless of enable/disable state
    if (empty($errors)) {
        if ($smtp_enabled) {
            $configContent = "<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit(translate('error_direct_access', 'Direct access to this file is not allowed.'));
}

define('SMTP_ENABLED', true);

use PHPMailer\\PHPMailer\\PHPMailer;
use PHPMailer\\PHPMailer\\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function getMailer(): PHPMailer {
    \$mail = new PHPMailer(true);
    try {
        \$mail->CharSet = 'UTF-8';
        \$mail->isSMTP();
        \$mail->Host       = '" . addslashes($smtpHost) . "';
        \$mail->SMTPAuth   = true;
        \$mail->Username   = '" . addslashes($smtpUser) . "';
        \$mail->Password   = '" . addslashes($smtpPass) . "';
        \$mail->SMTPSecure = '" . addslashes($smtpSecure) . "';
        \$mail->Port       = " . (int)$smtpPort . ";
        \$mail->setFrom('" . addslashes($smtpFrom) . "', '" . addslashes($smtpName) . "');
        \$mail->isHTML(true);
    } catch (Exception \$e) {}
    return \$mail;
}
?>";
        } else {
            $configContent = "<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit(translate('error_direct_access', 'Direct access to this file is not allowed.'));
}

use PHPMailer\\PHPMailer\\PHPMailer;
use PHPMailer\\PHPMailer\\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

\$smtp_enabled = false;
define('SMTP_ENABLED', \$smtp_enabled);

function getMailer(): PHPMailer {
    \$mail = new PHPMailer(true);
    return \$mail;
}
?>";
        }

        $configDir = dirname($configMailFile);
        if (!is_writable($configDir)) {
            $errors[] = translate('err_config_dir_not_writable', 'Config directory is not writable: %s', $configDir);
        } elseif (file_put_contents($configMailFile, $configContent) === false) {
            $errors[] = translate('err_failed_write_config', 'Failed to write config file: %s', $configMailFile);
        } else {
            $success = true;
            $smtp_status = $smtp_enabled ? 'enabled' : 'disabled';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($langCode); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo translate('page_title_smtp', 'SMTP Settings'); ?></title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <!-- Roboto font -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=block" rel="stylesheet">
    <style>
         body{
            background-color: #212529;
        }
        /* Main content */
        .main-content {
            padding-top: 80px; /* Clear header */
            min-height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .content {
            padding: 1.5rem;
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin: 1rem;
            color: #212529;
            text-align: center;
            flex-grow: 1;
        }

        /* Status, error, and success messages */
        .status-box, .error-box, .success-box {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: left;
        }

        .status-box {
            background: #e9ecef;
            border: 1px solid #ced4da;
        }

        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c2c7;
        }

        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
        }

        .db-status {
            display: flex;
            align-items: center;
            margin: 0.5rem 0;
        }

        .db-status-icon {
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }

        .db-status-success {
            color: #28a745;
        }

        .db-status-error {
            color: #dc3545;
        }

        .db-status-muted {
            color: #6c757d;
        }

        .error {
            color: #dc3545;
            font-weight: 500;
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 0.95rem;
        }

        .success, .text-success {
            color: #28a745;
            font-weight: 500;
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 0.95rem;
        }

        .text-muted {
            color: #6c757d;
            font-weight: 500;
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 0.95rem;
        }

        /* Form check (custom toggle switch) */
        .form-check {
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .form-check-input {
            width: 0;
            height: 0;
            opacity: 0;
            position: absolute;
        }

        .form-check-label {
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 0.95rem;
            color: #212529;
            cursor: pointer;
            padding-left: 3rem;
            user-select: none;
        }

        /* Toggle switch background */
        .form-check-label::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 2.5rem;
            height: 1.25rem;
            background: #6c757d; /* Gray when unchecked */
            border-radius: 1rem;
            transition: background-color 0.3s ease;
        }

        /* Toggle switch circle */
        .form-check-label::after {
            content: '';
            position: absolute;
            left: 0.2rem;
            top: 50%;
            transform: translateY(-50%);
            width: 1rem;
            height: 1rem;
            background: #ffffff;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            transition: left 0.3s ease;
        }

        /* Checked state */
        .form-check-input:checked + .form-check-label::before {
            background: #007bff; /* Blue when checked */
        }

        .form-check-input:checked + .form-check-label::after {
            left: 1.3rem; /* Slide circle to the right */
        }

        /* Hover effect */
        .form-check-label:hover::before {
            background: #0056b3; /* Darker blue on hover */
        }

        /* Focus state */
        .form-check-input:focus + .form-check-label::before {
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); /* Bootstrap-style focus ring */
        }

        /* Constrain form elements */
        .form-control, .form-select {
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .smtp-fields {
            display: none;
        }

        .smtp-fields.active {
            display: block;
        }

        /* Mobile adjustments */
        @media (max-width: 768px) {
            .main-content {
                padding-top: 60px; /* Adjust for mobile header and navbar */
                padding-left: 0;
                padding-right: 0;
            }

            .content {
                margin: 0.5rem;
                padding: 1rem;
            }

            .form-control, .form-select, .form-check, .status-box, .error-box, .success-box {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Admin Sidebar -->
            <?php include 'C:\xampp\htdocs\Sahtout\includes\admin_sidebar.php'; ?>
            
            <!-- Main Content with Settings Navbar -->
            <main class="col-md-10 main-content">
                <?php include 'C:\xampp\htdocs\Sahtout\pages\admin\settings\settings_navbar.php'; ?>
                <div class="content">
                    <h2><?php echo translate('settings_smtp', 'SMTP Settings'); ?></h2>
                    
                    <!-- Status Message -->
                    <div class="status-box mb-3 col-md-6 mx-auto">
                        <span class="db-status-icon <?php echo $smtp_status === 'enabled' ? 'db-status-success' : 'db-status-muted'; ?>">
                            <?php echo $smtp_status === 'enabled' ? '✔' : '✖'; ?>
                        </span>
                        <span class="<?php echo $smtp_status === 'enabled' ? 'text-success' : 'text-muted'; ?>">
                            <?php echo translate(
                                $smtp_status === 'enabled' ? 'msg_smtp_enabled' : 'msg_smtp_disabled',
                                $smtp_status === 'enabled' ? 'SMTP is currently enabled.' : 'SMTP is currently disabled.'
                            ); ?>
                        </span>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="error-box mb-3 col-md-6 mx-auto">
                            <strong><?php echo translate('err_fix_errors', 'Please fix the following errors:'); ?></strong>
                            <?php foreach ($errors as $err): ?>
                                <div class="db-status">
                                    <span class="db-status-icon db-status-error">❌</span>
                                    <span class="error"><?php echo htmlspecialchars($err); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="success-box mb-3 col-md-6 mx-auto">
                            <span class="db-status-icon db-status-success">✔</span>
                            <span class="success"><?php echo translate('msg_smtp_saved', 'SMTP settings saved successfully!'); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="mb-3 form-check">
                                    <input type="checkbox" id="smtp_enabled" name="smtp_enabled" class="form-check-input" <?php echo isset($_POST['smtp_enabled']) || $smtp_status === 'enabled' ? 'checked' : ''; ?>>
                                    <label for="smtp_enabled" class="form-check-label"><?php echo translate('label_smtp_enabled', 'Enable SMTP'); ?></label>
                                </div>

                                <div class="smtp-fields <?php echo isset($_POST['smtp_enabled']) || $smtp_status === 'enabled' ? 'active' : ''; ?>">
                                    <div class="mb-3">
                                        <label for="smtp_host" class="form-label"><?php echo translate('label_smtp_host', 'SMTP Host'); ?></label>
                                        <input type="text" id="smtp_host" name="smtp_host" class="form-control" placeholder="<?php echo translate('placeholder_smtp_host', 'e.g., smtp.gmail.com'); ?>" value="<?php echo htmlspecialchars($_POST['smtp_host'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="smtp_user" class="form-label"><?php echo translate('label_email_address', 'Email Address'); ?></label>
                                        <input type="email" id="smtp_user" name="smtp_user" class="form-control" placeholder="<?php echo translate('placeholder_email', 'e.g., yourname@gmail.com'); ?>" value="<?php echo htmlspecialchars($_POST['smtp_user'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="smtp_pass" class="form-label"><?php echo translate('label_app_password', 'App Password / SMTP Password'); ?></label>
                                        <input type="password" id="smtp_pass" name="smtp_pass" class="form-control" placeholder="<?php echo translate('placeholder_app_password', 'App password for Gmail/Outlook'); ?>" value="<?php echo htmlspecialchars($_POST['smtp_pass'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="smtp_from" class="form-label"><?php echo translate('label_from_email', 'From Email'); ?></label>
                                        <input type="email" id="smtp_from" name="smtp_from" class="form-control" placeholder="<?php echo translate('placeholder_from_email', 'e.g., noreply@yourdomain.com'); ?>" value="<?php echo htmlspecialchars($_POST['smtp_from'] ?? 'noreply@yourdomain.com'); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="smtp_name" class="form-label"><?php echo translate('label_from_name', 'From Name'); ?></label>
                                        <input type="text" id="smtp_name" name="smtp_name" class="form-control" placeholder="<?php echo translate('placeholder_from_name', 'e.g., Sahtout Account'); ?>" value="<?php echo htmlspecialchars($_POST['smtp_name'] ?? 'Sahtout Account'); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="smtp_port" class="form-label"><?php echo translate('label_port', 'Port'); ?></label>
                                        <input type="number" id="smtp_port" name="smtp_port" class="form-control" placeholder="<?php echo translate('placeholder_port_tls_ssl', '587 for TLS, 465 for SSL'); ?>" value="<?php echo htmlspecialchars($_POST['smtp_port'] ?? '587'); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="smtp_secure" class="form-label"><?php echo translate('label_encryption', 'Encryption (tls or ssl)'); ?></label>
                                        <input type="text" id="smtp_secure" name="smtp_secure" class="form-control" placeholder="<?php echo translate('placeholder_tls_or_ssl', 'tls or ssl'); ?>" value="<?php echo htmlspecialchars($_POST['smtp_secure'] ?? 'tls'); ?>">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary"><?php echo translate('btn_save_test_smtp', 'Save & Test SMTP'); ?></button>
                            </div>
                        </div>
                    </form>

                    <script>
                        document.getElementById('smtp_enabled').addEventListener('change', function() {
                            document.querySelector('.smtp-fields').classList.toggle('active', this.checked);
                        });
                    </script>
                </div>
            </main>
        </div>
    </div>
    <?php require_once 'C:\xampp\htdocs\Sahtout\includes\footer.php'; ?>
</body>
</html>