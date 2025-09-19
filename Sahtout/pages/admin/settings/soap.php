<?php
define('ALLOWED_ACCESS', true);
require_once 'C:\xampp\htdocs\Sahtout\includes\session.php';
require_once 'C:\xampp\htdocs\Sahtout\includes\config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header('Location: /Sahtout/login');
    exit;
}

$page_class = 'soap';
$errors = [];
$success = false;
$soapConfigFile = realpath('C:\xampp\htdocs\Sahtout\includes\soap.conf.php');

// Load current SOAP status
$soap_status = 'not_configured';
if (file_exists($soapConfigFile)) {
    include $soapConfigFile;
    if (!empty($soap_url) && !empty($soap_user) && !empty($soap_pass)) {
        $soap_status = 'configured';
    }
}

$soapUrl = $_POST['soap_url'] ?? 'http://127.0.0.1:7878';
$soapUser = $_POST['soap_user'] ?? '';
$soapPass = $_POST['soap_pass'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $soapUrl = trim($_POST['soap_url'] ?? 'http://127.0.0.1:7878');
    $soapUser = trim($_POST['soap_user'] ?? '');
    $soapPass = trim($_POST['soap_pass'] ?? '');

    // Validation
    if (empty($soapUrl)) {
        $errors[] = translate('error_soap_url_required', 'SOAP URL is required.');
    }
    if (empty($soapUser)) {
        $errors[] = translate('error_soap_user_required', 'GM Account Username is required.');
    }
    if (empty($soapPass)) {
        $errors[] = translate('error_soap_pass_required', 'SOAP Password is required.');
    }

    // Validate GM account
    if (empty($errors)) {
        $stmt = $auth_db->prepare("SELECT id FROM account WHERE username = ?");
        if (!$stmt) {
            $errors[] = translate('error_db_query', 'Database query error: %s', $auth_db->error);
        } else {
            $stmt->bind_param('s', $soapUser);
            $stmt->execute();
            $stmt->bind_result($accountId);
            $stmt->fetch();
            $stmt->close();

            if (!$accountId) {
                $errors[] = translate('error_account_not_exist', 'Account %s does not exist in Auth DB.', $soapUser);
            } else {
                $stmt2 = $auth_db->prepare("SELECT gmlevel FROM account_access WHERE id = ? AND RealmID = -1");
                if (!$stmt2) {
                    $errors[] = translate('error_db_query', 'Database query error: %s', $auth_db->error);
                } else {
                    $stmt2->bind_param('i', $accountId);
                    $stmt2->execute();
                    $stmt2->bind_result($gmLevel);
                    $stmt2->fetch();
                    $stmt2->close();

                    if (!$gmLevel || $gmLevel < 3) {
                        $errors[] = translate('error_account_not_gm_level_3', 'Account %s exists but is not GM level 3.', $soapUser);
                    }
                }
            }
        }
    }

    // Save settings if no errors
    if (empty($errors)) {
        $configContent = "<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}

\$soap_url  = '" . addslashes($soapUrl) . "';
\$soap_user = '" . addslashes($soapUser) . "'; // Must be GM level 3
\$soap_pass = '" . addslashes($soapPass) . "';
?>";

        $configDir = dirname($soapConfigFile);
        if (!is_writable($configDir)) {
            $errors[] = translate('error_config_dir_not_writable', 'Config directory is not writable: %s', $configDir);
        } elseif (file_put_contents($soapConfigFile, $configContent) === false) {
            $errors[] = translate('error_config_file_write_failed', 'Failed to write config file: %s', $soapConfigFile);
        } else {
            $success = true;
            $soap_status = 'configured';
        }
    }
}

require_once 'C:\xampp\htdocs\Sahtout\includes\header.php';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($langCode); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo translate('title_soap_settings', 'SOAP Settings'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', Arial, sans-serif;
            background-color: #212529;
            color: #212529;
        }
        .container-fluid {
            padding: 0;
        }
        .main-content {
            padding-top: 80px;
            min-height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
        }
        .content {
            padding: 1.5rem;
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin: 1rem;
            text-align: center;
            flex-grow: 1;
        }
        h2 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: #212529;
        }
        .status-box, .error-box, .success-box, .info-box {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: left;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
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
        .info-box {
            background: #e9ecef;
            border: 1px solid #ced4da;
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
            font-size: 0.95rem;
        }
        .success, .text-success {
            color: #28a745;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .text-muted {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .form-control {
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn-primary {
            max-width: 400px;
            width: 100%;
            margin-left: auto;
            margin-right: auto;
        }
        .info-title {
            font-weight: bold;
            color: #212529;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .info-content {
            display: none;
            color: #212529;
            line-height: 1.5;
        }
        @media (max-width: 768px) {
            .main-content {
                padding-top: 60px;
                padding-left: 0;
                padding-right: 0;
            }
            .content {
                margin: 0.5rem;
                padding: 1rem;
            }
            .form-control, .btn-primary, .status-box, .error-box, .success-box, .info-box {
                max-width: 100%;
            }
        }
    </style>
    <script>
        function toggleInfo(el) {
            const content = el.nextElementSibling;
            content.style.display = content.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../../includes/admin_sidebar.php'; ?>
            <main class="col-md-10 main-content">
                <?php include 'C:\xampp\htdocs\Sahtout\pages\admin\settings\settings_navbar.php'; ?>
                <div class="content">
                    <h2><?php echo translate('header_soap_settings', 'SOAP Settings'); ?></h2>

                    <!-- Status Message -->
                    <div class="status-box mb-3">
                        <span class="db-status-icon <?php echo $soap_status === 'configured' ? 'db-status-success' : 'db-status-muted'; ?>">
                            <?php echo $soap_status === 'configured' ? '✔' : '✖'; ?>
                        </span>
                        <span class="<?php echo $soap_status === 'configured' ? 'text-success' : 'text-muted'; ?>">
                            <?php echo $soap_status === 'configured' ? translate('status_soap_configured', 'SOAP is currently configured.') : translate('status_soap_not_configured', 'SOAP is not configured.'); ?>
                        </span>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="error-box mb-3">
                            <strong><?php echo translate('error_box_title', 'Please fix the following errors:'); ?></strong>
                            <?php foreach ($errors as $err): ?>
                                <div class="db-status">
                                    <span class="db-status-icon db-status-error">❌</span>
                                    <span class="error"><?php echo htmlspecialchars($err); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="success-box mb-3">
                            <span class="db-status-icon db-status-success">✔</span>
                            <span class="success"><?php echo translate('success_soap_settings_saved', 'SOAP settings saved successfully!'); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="soap_url" class="form-label"><?php echo translate('label_soap_url', 'SOAP URL'); ?></label>
                                    <input type="text" id="soap_url" name="soap_url" class="form-control" placeholder="<?php echo translate('placeholder_soap_url', 'e.g., http://127.0.0.1:7878'); ?>" value="<?php echo htmlspecialchars($soapUrl); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="soap_user" class="form-label"><?php echo translate('label_soap_user', 'GM Account Username'); ?></label>
                                    <input type="text" id="soap_user" name="soap_user" class="form-control" placeholder="<?php echo translate('placeholder_soap_user', 'Must be GM level 3'); ?>" value="<?php echo htmlspecialchars($soapUser); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="soap_pass" class="form-label"><?php echo translate('label_soap_pass', 'SOAP Password'); ?></label>
                                    <input type="password" id="soap_pass" name="soap_pass" class="form-control" placeholder="<?php echo translate('placeholder_soap_pass', 'SOAP password=Account password'); ?>" value="<?php echo htmlspecialchars($soapPass); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary"><?php echo translate('button_save_verify_soap', 'Save & Verify SOAP'); ?></button>
                            </div>
                        </div>
                    </form>

                    <div class="info-box mb-3">
                        <div class="info-title" onclick="toggleInfo(this)">
                            <?php echo translate('info_box_title', 'Important Steps (Click to expand)'); ?>
                        </div>
                        <div class="info-content">
                            <ul>
                                <li><?php echo translate('info_step_1', 'Make sure the GM account exists in your Auth DB and has GM level 3 in <code>account_access</code> with <code>RealmID = -1</code>.'); ?></li>
                                <li><?php echo translate('info_step_2', 'Open your <code>worldserver.conf</code> file and set: <strong>SOAP.Enabled = 1</strong>'); ?></li>
                                <li><?php echo translate('info_step_3', 'Ensure the SOAP port in <code>soap_url</code> is correct and accessible.'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <?php include 'C:\xampp\htdocs\Sahtout\includes\footer.php'; ?>
</body>
</html>