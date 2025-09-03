<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/header.inc.php';
require_once __DIR__ . '/languages/language.php';

$errors = [];
$success = false;
$configFile = realpath(__DIR__ . '/../includes/config.php');
$configCapFile = realpath(__DIR__ . '/../includes/config.cap.php');

$default_site_key = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';
$default_secret_key = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = trim($_POST['db_pass'] ?? '');
    $dbAuth = trim($_POST['db_auth'] ?? '');
    $dbWorld = trim($_POST['db_world'] ?? '');
    $dbChar = trim($_POST['db_char'] ?? '');
    $dbSite = trim($_POST['db_site'] ?? 'sahtout_site');
    $recaptcha_enabled = isset($_POST['recaptcha_enabled']) ? 1 : 0;
    $recaptcha_site_key = $recaptcha_enabled ? trim($_POST['recaptcha_site_key'] ?? '') : '';
    $recaptcha_secret_key = $recaptcha_enabled ? trim($_POST['recaptcha_secret_key'] ?? '') : '';

    // Use default keys if none provided and reCAPTCHA is enabled
    if ($recaptcha_enabled && empty($recaptcha_site_key)) {
        $recaptcha_site_key = $default_site_key;
    }
    if ($recaptcha_enabled && empty($recaptcha_secret_key)) {
        $recaptcha_secret_key = $default_secret_key;
    }

    if (empty($dbHost)) $errors[] = translate('err_db_host_required', 'Database host is required');
    if (empty($dbUser)) $errors[] = translate('err_db_user_required', 'Database username is required');
    if (empty($dbAuth)) $errors[] = translate('err_db_auth_required', 'Auth database name is required');
    if (empty($dbWorld)) $errors[] = translate('err_db_world_required', 'World database name is required');
    if (empty($dbChar)) $errors[] = translate('err_db_char_required', 'Character database name is required');
    if (empty($dbSite)) $errors[] = translate('err_db_site_required', 'Site database name is required');
    if ($recaptcha_enabled && (empty($recaptcha_site_key) || empty($recaptcha_secret_key))) {
        $errors[] = translate('err_recaptcha_keys_required', 'reCAPTCHA Site Key and Secret Key are required when reCAPTCHA is enabled.');
    }

    if (empty($errors)) {
        $dbConns = [
            'Auth DB' => [$dbAuth, null, 'auth'],
            'World DB' => [$dbWorld, null, 'world'],
            'Char DB' => [$dbChar, null, 'char'],
            'Site DB' => [$dbSite, null, 'site'],
        ];

        foreach ($dbConns as $name => $connInfo) {
            try {
                $conn = new mysqli($dbHost, $dbUser, $dbPass, $connInfo[0]);
                $dbConns[$name][1] = $conn;

                $requiredTables = [];
                switch ($connInfo[2]) {
                    case 'auth':
                        $requiredTables = ['account', 'realmcharacters'];
                        break;
                    case 'world':
                        $requiredTables = ['creature_template', 'item_template'];
                        break;
                    case 'char':
                        $requiredTables = ['characters', 'character_inventory'];
                        break;
                }

                foreach ($requiredTables as $table) {
                    $result = $conn->query("SHOW TABLES LIKE '$table'");
                    if (!$result || $result->num_rows === 0) {
                        $errors[] = "❌ " . translate('err_missing_table', 'Missing required table: %s', $table);
                    }
                    if ($result) $result->free();
                }
            } catch (Exception $e) {
                $errors[] = "❌ {$name} " . translate('err_connection_failed', 'Connection failed: %s', $e->getMessage());
            }
        }

        if (empty($errors)) {
            $configContent = "<?php
if (!defined('ALLOWED_ACCESS')) exit('Direct access not allowed.');
\$db_host = '" . addslashes($dbHost) . "';
\$db_user = '" . addslashes($dbUser) . "';
\$db_pass = '" . addslashes($dbPass) . "';
\$db_auth = '" . addslashes($dbAuth) . "';
\$db_world = '" . addslashes($dbWorld) . "';
\$db_char = '" . addslashes($dbChar) . "';
\$db_site = '" . addslashes($dbSite) . "';

\$auth_db = new mysqli(\$db_host,\$db_user,\$db_pass,\$db_auth);
\$world_db = new mysqli(\$db_host,\$db_user,\$db_pass,\$db_world);
\$char_db = new mysqli(\$db_host,\$db_user,\$db_pass,\$db_char);
\$site_db = new mysqli(\$db_host,\$db_user,\$db_pass,\$db_site);

if (\$auth_db->connect_error) die('Auth DB Connection failed: ' . \$auth_db->connect_error);
if (\$world_db->connect_error) die('World DB Connection failed: ' . \$world_db->connect_error);
if (\$char_db->connect_error) die('Char DB Connection failed: ' . \$char_db->connect_error);
if (\$site_db->connect_error) die('Site DB Connection failed: ' . \$site_db->connect_error);
?>";

            $capConfigContent = "<?php
if (!defined('ALLOWED_ACCESS')) exit('Direct access not allowed.');
\$recaptcha_enabled = " . ($recaptcha_enabled ? 'true' : 'false') . ";
\$recaptcha_site_key = '" . addslashes($recaptcha_site_key) . "';
\$recaptcha_secret_key = '" . addslashes($recaptcha_secret_key) . "';
define('RECAPTCHA_ENABLED', \$recaptcha_enabled);
define('RECAPTCHA_SITE_KEY', \$recaptcha_site_key);
define('RECAPTCHA_SECRET_KEY', \$recaptcha_secret_key);
?>";

            $configDir = dirname($configFile);
            $capConfigDir = dirname($configCapFile);

            if (!is_writable($configDir)) {
                $errors[] = "⚠️ " . translate('err_config_dir_not_writable', 'Config directory is not writable: %s', $configDir);
            } elseif (!is_writable($capConfigDir)) {
                $errors[] = "⚠️ " . translate('err_cap_dir_not_writable', 'reCAPTCHA config directory is not writable: %s', $capConfigDir);
            } else {
                if (file_put_contents($configFile, $configContent) === false) {
                    $errors[] = "⚠️ " . translate('err_failed_write_config', 'Failed to write config file: %s', $configFile);
                }
                if (file_put_contents($configCapFile, $capConfigContent) === false) {
                    $errors[] = "⚠️ " . translate('err_failed_write_cap', 'Failed to write reCAPTCHA config file: %s', $capConfigDir);
                }

                if (empty($errors)) {
                    $success = true;
                }
            }
        }

        foreach ($dbConns as $name => $connInfo) {
            if ($connInfo[1] instanceof mysqli && !$connInfo[1]->connect_error) {
                try {
                    $connInfo[1]->close();
                } catch (Exception $e) {
                    $errors[] = translate('err_close_connection', 'Failed to close connection: %s', $name . ': ' . $e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($langCode ?? 'en') ?>">
<head>
    <meta charset="UTF-8">
    <title><?= translate('installer_title', 'SahtoutCMS Installer') ?> - <?= translate('step3_title', 'Step 3: Database & reCAPTCHA Setup') ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Cinzel', serif;
            background: #0a0a0a;
            color: #f0e6d2;
        }
        .overlay {
            background: rgba(0,0,0,0.9);
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            text-align: center;
            max-width: 700px;
            width: 100%;
            min-height: 70vh;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px 20px;
            border: 2px solid #6b4226;
            background: rgba(20,10,5,0.95);
            border-radius: 12px;
            box-shadow: 0 0 30px #6b4226;
        }
        h1 {
            font-size: 2.5em;
            margin-bottom: 20px;
            color: #d4af37;
            text-shadow: 0 0 10px #000;
        }
        label {
            display: block;
            text-align: left;
            margin: 10px 0 5px;
            font-family: 'Roboto', Arial, sans-serif;
            color: #f0e6d2;
        }
        input {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #6b4226;
            background: rgba(30,15,5,0.9);
            color: #f0e6d2;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            font-size: 1.2em;
            font-weight: bold;
            color: #fff;
            background: linear-gradient(135deg,#6b4226,#a37e2c);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 0 15px #a37e2c;
            transition: 0.3s ease;
            margin-top: 15px;
        }
        .btn:hover {
            background: linear-gradient(135deg,#a37e2c,#d4af37);
            box-shadow: 0 0 25px #d4af37;
        }
        .error-box {
            background: rgba(255,64,64,0.2);
            border: 1px solid #ff4040;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 20px;
            text-align: left;
        }
        .error {
            color: #ff4040;
            font-weight: bold;
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 1rem;
            margin: 5px 0;
        }
        .success {
            color: #7CFC00;
            font-weight: bold;
            margin-top: 15px;
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 1rem;
        }
        .section-title {
            margin-top: 30px;
            font-size: 1.5em;
            color: #d4af37;
            text-decoration: underline;
        }
        .note {
            font-size: 0.9em;
            color: #a37e2c;
            margin-top: 10px;
            text-align: left;
            font-family: 'Roboto', Arial, sans-serif;
        }
        .recaptcha-fields {
            display: none;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        .recaptcha-fields.active {
            display: block;
        }
        /* Custom toggle switch */
        .form-check {
            max-width: 400px;
            margin: 20px auto;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .form-check-input {
            width: 0;
            height: 0;
            opacity: 0;
            position: absolute;
        }
        .form-check-label {
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 1rem;
            color: #f0e6d2;
            cursor: pointer;
            padding-left: 3.5rem;
            user-select: none;
            position: relative;
        }
        .form-check-label::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3rem;
            height: 1.5rem;
            background: #6c757d; /* Gray when unchecked */
            border-radius: 1.5rem;
            transition: background-color 0.3s ease;
        }
        .form-check-label::after {
            content: '';
            position: absolute;
            left: 0.25rem;
            top: 50%;
            transform: translateY(-50%);
            width: 1.25rem;
            height: 1.25rem;
            background: #ffffff;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            transition: left 0.3s ease;
        }
        .form-check-input:checked + .form-check-label::before {
            background: #28a745; /* Green when checked */
        }
        .form-check-input:checked + .form-check-label::after {
            left: 1.5rem; /* Slide circle to the right */
        }
        .form-check-label:hover::before {
            background: #1e7e34; /* Darker green on hover when checked */
        }
        .form-check-input:not(:checked) + .form-check-label:hover::before {
            background: #5a6268; /* Darker gray on hover when unchecked */
        }
        .form-check-input:focus + .form-check-label::before {
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25); /* Focus ring */
        }
        .form-check-status {
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 1rem;
            color: #f0e6d2;
        }
        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
                margin: 15px;
            }
            h1 {
                font-size: 2em;
            }
            input, .form-check {
                max-width: 100%;
            }
            .btn {
                padding: 10px 20px;
                font-size: 1.1em;
            }
            .error-box {
                margin: 15px;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <script>
        function toggleRecaptchaFields() {
            const fields = document.querySelector('.recaptcha-fields');
            const enabled = document.getElementById('recaptcha_enabled').checked;
            fields.classList.toggle('active', enabled);
            document.querySelector('.form-check-status').textContent = enabled ? '<?= translate('enabled', 'Enabled') ?>' : '<?= translate('missing', 'Disabled') ?>';
        }
    </script>
</head>
<body onload="toggleRecaptchaFields()">
<div class="overlay">
    <div class="container">
        <h1>⚔️ <?= translate('installer_name', 'SahtoutCMS Installer') ?></h1>
        <h2 class="section-title"><?= translate('step3_title', 'Step 3: Database & reCAPTCHA Setup') ?></h2>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <strong><?= translate('err_fix_errors', 'Please fix the following errors:') ?></strong>
                <?php foreach ($errors as $err): ?>
                    <div class="db-status">
                        <span class="db-status-icon db-status-error">❌</span>
                        <span class="error"><?= htmlspecialchars($err) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($success): ?>
            <div class="db-status">
                <span class="db-status-icon db-status-success">✔</span>
                <span class="success"><?= translate('msg_config_saved', 'All databases connected successfully! Config and reCAPTCHA files created.') ?></span>
            </div>
            <a href="step4_realm.php" class="btn"><?= translate('btn_proceed_to_realm', 'Proceed to Step 4 Realm configuration ➡️') ?></a>
        <?php endif; ?>

        <?php if (!$success): ?>
            <form method="post">
                <div class="section-title"><?= translate('db_credentials', 'Database Credentials') ?></div>
                <label for="db_host"><?= translate('label_db_host', 'Database Host') ?></label>
                <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>

                <label for="db_user"><?= translate('label_db_user', 'Database Username') ?></label>
                <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>

                <label for="db_pass"><?= translate('label_db_pass', 'Database Password') ?></label>
                <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">

                <label for="db_auth"><?= translate('label_db_auth', 'Auth DB Name') ?></label>
                <input type="text" id="db_auth" name="db_auth" value="<?= htmlspecialchars($_POST['db_auth'] ?? '') ?>" required>

                <label for="db_world"><?= translate('label_db_world', 'World DB Name') ?></label>
                <input type="text" id="db_world" name="db_world" value="<?= htmlspecialchars($_POST['db_world'] ?? '') ?>" required>

                <label for="db_char"><?= translate('label_db_char', 'Char DB Name') ?></label>
                <input type="text" id="db_char" name="db_char" value="<?= htmlspecialchars($_POST['db_char'] ?? '') ?>" required>

                <label for="db_site"><?= translate('label_db_site', 'Site DB Name') ?></label>
                <input type="text" id="db_site" name="db_site" value="<?= htmlspecialchars($_POST['db_site'] ?? 'sahtout_site') ?>" required>
                <p class="note"><?= translate('note_site_db', '“sahtout_site” is recommended for the site database name') ?></p>

                <div class="section-title"><?= translate('section_recaptcha', 'reCAPTCHA V2 Checkbox Configuration') ?></div>
                <div class="form-check">
                    <input type="checkbox" id="recaptcha_enabled" name="recaptcha_enabled" class="form-check-input" onclick="toggleRecaptchaFields()" <?php echo isset($_POST['recaptcha_enabled']) ? 'checked' : ''; ?>>
                    <label for="recaptcha_enabled" class="form-check-label"><?= translate('label_recaptcha_enabled', 'Enable reCAPTCHA') ?></label>
                    <span class="form-check-status"><?php echo isset($_POST['recaptcha_enabled']) ? translate('captcha_enabled', 'Enabled') : translate('captcha_missing', 'Disabled'); ?></span>
                </div>
                <div class="recaptcha-fields <?php echo isset($_POST['recaptcha_enabled']) ? 'active' : ''; ?>">
                    <label for="recaptcha_site_key"><?= translate('label_recaptcha_site_key', 'Site Key') ?></label>
                    <input type="text" id="recaptcha_site_key" name="recaptcha_site_key" placeholder="<?= translate('placeholder_recaptcha_default', 'Leave empty for default') ?>" value="<?= htmlspecialchars($_POST['recaptcha_site_key'] ?? '') ?>">

                    <label for="recaptcha_secret_key"><?= translate('label_recaptcha_secret_key', 'Secret Key') ?></label>
                    <input type="text" id="recaptcha_secret_key" name="recaptcha_secret_key" placeholder="<?= translate('placeholder_recaptcha_default', 'Leave empty for default') ?>" value="<?= htmlspecialchars($_POST['recaptcha_secret_key'] ?? '') ?>">
                    <p class="note"><?= translate('note_recaptcha_empty', 'Leave reCAPTCHA fields empty to use default keys when enabled') ?></p>
                </div>

                <button type="submit" class="btn"><?= translate('btn_test_save_db', 'Test & Save Database and reCAPTCHA Settings') ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/footer.inc.php'; ?>
</body>
</html>