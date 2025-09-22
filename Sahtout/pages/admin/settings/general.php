<?php
define('ALLOWED_ACCESS', true);
require_once dirname(__DIR__, 3) . '/includes/session.php';
require_once dirname(__DIR__, 3) . '/languages/language.php';
require_once dirname(__DIR__, 3) . '/includes/config.settings.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header('Location: '.SUBDIR.'login');
    exit;
}

$page_class = 'general';
require_once dirname(__DIR__, 3) . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($langCode); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo translate('page_title_general', 'General Settings'); ?></title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <!-- Roboto font -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body{
            background-color: #212529;
        }
        .main-content {
            padding-top: 80px;
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
        .error {
            color: #dc3545;
            font-weight: 500;
        }
        .success {
            color: #28a745;
            font-weight: 500;
        }
        .input-group-text.social-icon {
            width: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #212529;
            background-color: #f8f9fa;
        }
        .social-icon i {
            font-size: 20px;
        }
        .social-icon .kick-icon1 {
            width: 20px;
            height: 12px;
            filter: none;
        }
        /* Custom file input styling */
        .custom-file-upload {
            display: inline-block;
            width: 100%;
            text-align: left;
        }
        .custom-file-upload input[type="file"] {
            display: none;
        }
        .custom-file-upload .btn {
            width: 200px;
            display: flex;
            margin: auto;
            justify-content: center;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            border: 1px solid #ced4da;
            color: #ffffffff;
            background-color: #0b71e6ff;
            transition: all 0.3s ease;
        }
        .custom-file-upload .btn:hover {
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 0.95rem;
        }
        .custom-file-upload .file-name {
            margin-top: 0.5rem;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
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
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Admin Sidebar -->
            <?php include dirname(__DIR__, 3) . '/includes/admin_sidebar.php'; ?>
            
            <!-- Main Content with Settings Navbar -->
            <main class="col-md-10 main-content">
                <?php include dirname(__DIR__) . '/settings/settings_navbar.php'; ?>
                <div class="content">
                    <h2><?php echo translate('settings_general', 'General Settings'); ?></h2>

                    <!-- Success/Error Messages -->
                    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
                        <div class="success-box mb-3 col-md-6 mx-auto">
                            <span class="db-status-icon db-status-success">✔</span>
                            <span class="success"><?php echo translate('msg_settings_saved', 'Settings updated successfully!'); ?></span>
                        </div>
                    <?php elseif (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
                        <div class="error-box mb-3 col-md-6 mx-auto">
                            <strong><?php echo translate('err_fix_errors', 'Please fix the following errors:'); ?></strong>
                            <div class="db-status">
                                <span class="db-status-icon db-status-error">❌</span>
                                <span class="error"><?php echo htmlspecialchars(urldecode($_GET['message'])); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- General Settings Form -->
                    <div class="row justify-content-center">
                        <form action="<?php echo SUBDIR ?>pages/admin/settings/save_general.php" method="POST" enctype="multipart/form-data" class="col-md-6">
                            <!-- CSRF Token -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <!-- Max File Size (3MB) -->
                            <input type="hidden" name="MAX_FILE_SIZE" value="3145728">

                            <!-- Logo Upload -->
                            <div class="mb-3">
                                <label for="logo" class="form-label"><?php echo translate('label_website_logo', 'Website Logo'); ?></label>
                                <div class="mb-2">
                                    <img src="<?php echo $site_logo; ?>" alt="<?php echo translate('label_website_logo', 'Website Logo'); ?>" class="img-fluid" style="max-width: 150px;">
                                </div>
                                <div class="custom-file-upload">
                                    <input type="file" id="logo" name="logo" accept="image/png,image/svg+xml,image/jpeg">
                                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('logo').click();"><?php echo translate('btn_choose_file', 'Choose File'); ?></button>
                                    <div class="file-name" id="file-name"><?php echo translate('placeholder_logo', 'Upload a PNG, SVG, or JPG image (max 3MB) to be used as your website logo.'); ?></div>
                                </div>
                            </div>

                            <!-- Social Links -->
                            <div class="mb-3">
                                <label class="form-label"><?php echo translate('label_social_media', 'Social Media Links'); ?></label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text social-icon"><i class="fab fa-facebook-f"></i></span>
                                    <input type="url" name="facebook" class="form-control" placeholder="<?php echo translate('placeholder_facebook', 'Facebook URL'); ?>" value="<?php echo htmlspecialchars($social_links['facebook']); ?>">
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text social-icon"><i class="fab fa-x-twitter"></i></span>
                                    <input type="url" name="twitter" class="form-control" placeholder="<?php echo translate('placeholder_twitter', 'Twitter (X) URL'); ?>" value="<?php echo htmlspecialchars($social_links['twitter'] ?? ''); ?>">
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text social-icon"><i class="fab fa-tiktok"></i></span>
                                    <input type="url" name="tiktok" class="form-control" placeholder="<?php echo translate('placeholder_tiktok', 'TikTok URL'); ?>" value="<?php echo htmlspecialchars($social_links['tiktok'] ?? ''); ?>">
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text social-icon"><i class="fab fa-youtube"></i></span>
                                    <input type="url" name="youtube" class="form-control" placeholder="<?php echo translate('placeholder_youtube', 'YouTube URL'); ?>" value="<?php echo htmlspecialchars($social_links['youtube']); ?>">
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text social-icon"><i class="fab fa-discord"></i></span>
                                    <input type="url" name="discord" class="form-control" placeholder="<?php echo translate('placeholder_discord', 'Discord Invite URL'); ?>" value="<?php echo htmlspecialchars($social_links['discord']); ?>">
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text social-icon"><i class="fab fa-twitch"></i></span>
                                    <input type="url" name="twitch" class="form-control" placeholder="<?php echo translate('placeholder_twitch', 'Twitch URL'); ?>" value="<?php echo htmlspecialchars($social_links['twitch']); ?>">
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text social-icon"><img src="<?php echo SUBDIR ?>img/icons/kick-logo.png" alt="Kick" class="kick-icon1"></span>
                                    <input type="url" name="kick" class="form-control" placeholder="<?php echo translate('placeholder_kick', 'Kick URL'); ?>" value="<?php echo htmlspecialchars($social_links['kick']); ?>">
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text social-icon"><i class="fab fa-instagram"></i></span>
                                    <input type="url" name="instagram" class="form-control" placeholder="<?php echo translate('placeholder_instagram', 'Instagram URL'); ?>" value="<?php echo htmlspecialchars($social_links['instagram']); ?>">
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text social-icon"><i class="fab fa-github"></i></span>
                                    <input type="url" name="github" class="form-control" placeholder="<?php echo translate('placeholder_github', 'GitHub URL'); ?>" value="<?php echo htmlspecialchars($social_links['github']); ?>">
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text social-icon"><i class="fab fa-linkedin-in"></i></span>
                                    <input type="url" name="linkedin" class="form-control" placeholder="<?php echo translate('placeholder_linkedin', 'LinkedIn URL'); ?>" value="<?php echo htmlspecialchars($social_links['linkedin']); ?>">
                                </div>
                            </div>

                            <!-- Save Button -->
                            <button type="submit" class="btn btn-primary"><?php echo translate('btn_save_settings', 'Save Settings'); ?></button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
    <script>
        // Update file name display when a file is selected
        document.getElementById('logo').addEventListener('change', function() {
            const fileName = this.files.length > 0 ? this.files[0].name : '<?php echo translate('placeholder_logo', 'Upload a PNG, SVG, or JPG image (max 3MB) to be used as your website logo.'); ?>';
            document.getElementById('file-name').textContent = fileName;
        });
    </script>
</body>
</html>