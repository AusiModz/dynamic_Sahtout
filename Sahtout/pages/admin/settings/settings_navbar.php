<?php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    header('HTTP/1.1 403 Forbidden');
    exit(translate('error_direct_access', 'Direct access to this file is not allowed.'));
}

$page_class = $page_class ?? '';
?>

<!-- Settings Navbar -->
<nav class="settings-nav">
    <div class="settings-nav-container">
        <h5 class="settings-title"><?php echo translate('settings_nav_menu', 'Settings Menu'); ?></h5>
        <button class="mobile-toggle" aria-label="Toggle settings navigation">
            <i class="fas fa-bars"></i>
        </button>
        <ul class="settings-nav-tabs">
            <li><a class="nav-link <?php echo $page_class === 'general' ? 'active' : ''; ?>" href="/Sahtout/admin/settings/general"><i class="fas fa-cog me-1"></i> <?php echo translate('settings_nav_general', 'General'); ?></a></li>
            <li><a class="nav-link <?php echo $page_class === 'smtp' ? 'active' : ''; ?>" href="/Sahtout/admin/settings/smtp"><i class="fas fa-envelope me-1"></i> <?php echo translate('settings_nav_smtp', 'SMTP'); ?></a></li>
            <li><a class="nav-link <?php echo $page_class === 'recaptcha' ? 'active' : ''; ?>" href="/Sahtout/admin/settings/recaptcha"><i class="fas fa-shield-alt me-1"></i> <?php echo translate('settings_nav_recaptcha', 'reCAPTCHA'); ?></a></li>
            <li><a class="nav-link <?php echo $page_class === 'realm' ? 'active' : ''; ?>" href="/Sahtout/admin/settings/realm"><i class="fas fa-server me-1"></i> <?php echo translate('settings_nav_realm', 'Realm'); ?></a></li>
            <li><a class="nav-link <?php echo $page_class === 'soap' ? 'active' : ''; ?>" href="/Sahtout/admin/settings/soap"><i class="fas fa-code me-1"></i> <?php echo translate('settings_nav_soap', 'SOAP'); ?></a></li>
        </ul>
    </div>
</nav>

<style>
/* Container */
.settings-nav {
    margin-bottom: 1rem;
}

.settings-nav-container {
    background: #343a40;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 0.75rem 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-top: -70px;
}

/* Title */
.settings-title {
    color: #ffffff;
    font-family: 'Roboto', Arial, sans-serif;
    font-size: 1.2rem;
    font-weight: 500;
    margin: 0;
    text-align: center;
}

/* Nav tabs */
.settings-nav-tabs {
    display: flex;
    gap: 0.5rem;
    list-style: none;
    margin: 0.5rem 0 0;
    padding: 0;
    justify-content: center;
}

.settings-nav-tabs .nav-link {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.7rem 1rem;
    border-radius: 6px;
    font-size: 0.95rem;
    font-weight: 500;
    color: #ffffff;
    background: #495057;
    border: 1px solid #dee2e6;
    text-decoration: none;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.settings-nav-tabs .nav-link:hover {
    background: #6c757d;
    color: #ffffff;
}

.settings-nav-tabs .nav-link.active {
    background: #007bff;
    color: #ffffff;
    font-weight: 600;
    border-color: #0056b3;
}

.settings-nav-tabs .nav-link i {
    width: 22px;
    text-align: center;
    font-size: 1rem;
}

/* Mobile toggle */
.mobile-toggle {
    display: none;
    background: none;
    border: none;
    color: #ffffff;
    font-size: 1.5rem;
    cursor: pointer;
    position: absolute;
    right: 0.4rem;
    top: 0.1rem;
}

/* Responsive */
@media (max-width: 768px) {
    .settings-nav-container {
        position: relative;
        padding: 0.75rem;
    }

    .settings-title {
        font-size: 1.1rem;
    }

    .mobile-toggle {
        display: block;
    }

    .settings-nav-tabs {
        display: none;
        flex-direction: column;
        width: 100%;
        margin-top: 0.75rem;
        background: #495057;
        border: 1px solid #dee2e6;
        border-top: none;
        padding: 0.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .settings-nav-tabs.active {
        display: flex;
    }

    .settings-nav-tabs .nav-link {
        width: 100%;
        padding: 0.6rem 0.8rem;
        font-size: 0.9rem;
    }

    .mobile-toggle.active .fa-bars::before {
        content: "\f00d";
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleButton = document.querySelector('.settings-nav .mobile-toggle');
    const navTabs = document.querySelector('.settings-nav-tabs');
    
    toggleButton.addEventListener('click', function() {
        navTabs.classList.toggle('active');
        toggleButton.classList.toggle('active');
    });
});
</script>