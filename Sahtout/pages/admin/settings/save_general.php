<?php
define('ALLOWED_ACCESS', true);
require_once dirname(__DIR__, 3) . '/includes/session.php';
require_once dirname(__DIR__, 3) . '/languages/language.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    $_SESSION['debug_errors'] = [translate('error_access_denied', 'Access denied.')];
    header('Location: '.SUBDIR.'login');
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['debug_errors'] = [translate('error_csrf_invalid', 'Invalid CSRF token.')];
    header('Location: '.SUBDIR.'admin/settings/general?status=error&message=' . urlencode(translate('error_csrf_invalid', 'Invalid CSRF token.')));
    exit;
}

require_once dirname(__DIR__, 3) . '/includes/config.settings.php';

// Initialize variables
$errors = [];
$success = false;

// Handle logo upload
$logo_path = $site_logo; // Default to existing logo
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['logo']['tmp_name'];
    $file_name = $_FILES['logo']['name'];
    $file_size = $_FILES['logo']['size'];
    $file_type = $_FILES['logo']['type'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_exts = ['png', 'svg', 'jpg', 'jpeg'];
    $max_size = 3 * 1024 * 1024; // 3MB in bytes

    // Validate file size
    if ($file_size > $max_size) {
        $errors[] = translate('error_file_too_large', 'File size exceeds 3MB.');
    }
    // Validate extension
    elseif (!in_array($file_ext, $allowed_exts)) {
        $errors[] = translate('error_invalid_file_type', 'Invalid file type. Only PNG, SVG, or JPG allowed.');
    }
    // Validate MIME type
    elseif ($file_ext === 'png' && $file_type !== 'image/png') {
        $errors[] = translate('error_invalid_file_type', 'Invalid PNG file. MIME type must be image/png.');
    }
    elseif (in_array($file_ext, ['jpg', 'jpeg']) && !in_array($file_type, ['image/jpeg', 'image/jpg'])) {
        $errors[] = translate('error_invalid_file_type', 'Invalid JPG file. MIME type must be image/jpeg or image/jpg.');
    }
    elseif ($file_ext === 'svg' && $file_type !== 'image/svg+xml') {
        $errors[] = translate('error_invalid_file_type', 'Invalid SVG file. MIME type must be image/svg+xml.');
    }
    else {
        $upload_dir = dirname(__DIR__, 3) . '/img/';
        if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
            $errors[] = translate('error_file_upload_failed', 'Upload directory is not accessible or writable.');
        } else {
            $new_file_name = 'logo.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $destination)) {
                $logo_path = "img/$new_file_name";
            } else {
                $errors[] = translate('error_file_upload_failed', 'Failed to upload logo. Check server permissions.');
            }
        }
    }
} elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    switch ($_FILES['logo']['error']) {
        case UPLOAD_ERR_INI_SIZE:
            $errors[] = translate('error_file_too_large', 'Logo file exceeds server upload limit (3MB).');
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $errors[] = translate('error_file_too_large', 'Logo file exceeds form limit (3MB).');
            break;
        case UPLOAD_ERR_PARTIAL:
            $errors[] = translate('error_file_upload_failed', 'Logo file was only partially uploaded.');
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errors[] = translate('error_file_upload_failed', 'Server error: Temporary directory missing.');
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errors[] = translate('error_file_upload_failed', 'Server error: Failed to write file to disk.');
            break;
        default:
            $errors[] = translate('error_file_upload_failed', 'Error uploading logo: Code ' . $_FILES['logo']['error']);
    }
}

// Handle social links
$social_links_new = [
    'facebook'  => filter_input(INPUT_POST, 'facebook', FILTER_VALIDATE_URL) ?: '',
    'twitter'   => filter_input(INPUT_POST, 'twitter', FILTER_VALIDATE_URL) ?: '',
    'tiktok'    => filter_input(INPUT_POST, 'tiktok', FILTER_VALIDATE_URL) ?: '',
    'youtube'   => filter_input(INPUT_POST, 'youtube', FILTER_VALIDATE_URL) ?: '',
    'discord'   => filter_input(INPUT_POST, 'discord', FILTER_VALIDATE_URL) ?: '',
    'twitch'    => filter_input(INPUT_POST, 'twitch', FILTER_VALIDATE_URL) ?: '',
    'kick'      => filter_input(INPUT_POST, 'kick', FILTER_VALIDATE_URL) ?: '',
    'instagram' => filter_input(INPUT_POST, 'instagram', FILTER_VALIDATE_URL) ?: '',
    'github'    => filter_input(INPUT_POST, 'github', FILTER_VALIDATE_URL) ?: '',
    'linkedin'  => filter_input(INPUT_POST, 'linkedin', FILTER_VALIDATE_URL) ?: '',
];

// Validate social links
foreach ($social_links_new as $platform => $url) {
    if (!empty($url) && $url === '') {
        $errors[] = translate("error_invalid_$platform", "Invalid $platform URL.");
    }
}

// Update config.settings.php
if (empty($errors)) {
    $config_file = dirname(__DIR__, 3) . '/includes/config.settings.php';
    if (!is_writable($config_file)) {
        $errors[] = translate('error_file_write_failed', 'Configuration file is not writable.');
    } else {
        $config_content = "<?php\n";
        $config_content .= "if (!defined('ALLOWED_ACCESS')) {\n";
        $config_content .= "    header('HTTP/1.1 403 Forbidden');\n";
        $config_content .= "    exit('Direct access not allowed.');\n";
        $config_content .= "}\n\n";
        $config_content .= "// Logo\n";
        $config_content .= "\$site_logo = " . var_export($logo_path, true) . ";\n\n";
        $config_content .= "// Social links\n";
        $config_content .= "\$social_links = [\n";
        foreach ($social_links_new as $key => $value) {
            $config_content .= "    '$key' => " . var_export($value, true) . ",\n";
        }
        $config_content .= "];\n";

        if (file_put_contents($config_file, $config_content)) {
            $success = true;
        } else {
            $errors[] = translate('error_file_write_failed', 'Failed to update configuration file.');
        }
    }
}

// Regenerate CSRF token after form submission
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Redirect with feedback
$redirect_url = SUBDIR.'admin/settings/general';
if ($success) {
    header("Location: $redirect_url?status=success");
} else {
    $_SESSION['debug_errors'] = $errors;
    header("Location: $redirect_url?status=error&message=" . urlencode(implode(', ', $errors)));
}
exit;
?>