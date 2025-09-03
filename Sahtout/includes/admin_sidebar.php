<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}
?>
<?php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'moderator'])) {
    exit;
}
$page_class = $page_class ?? '';
?>

<aside class="col-md-2 admin-sidebar">
    <div class="card admin-sidebar-card">
        <div class="card-header admin-sidebar-header">
            <h5 class="mb-0"><?php echo translate('admin_menu', 'Admin Menu'); ?></h5>
            <button class="mobile-toggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="card-body p-2 admin-sidebar-menu">
            <ul class="nav flex-column admin-sidebar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'dashboard' ? 'active' : ''; ?>" href="/Sahtout/admin/dashboard">
                        <i class="fas fa-tachometer-alt me-2"></i> <?php echo translate('admin_dashboard', 'Dashboard'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'users' ? 'active' : ''; ?>" href="/Sahtout/admin/users">
                        <i class="fas fa-users me-2"></i> <?php echo translate('admin_users', 'User Management'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'anews' ? 'active' : ''; ?>" href="/Sahtout/admin/anews">
                        <i class="fas fa-newspaper me-2"></i> <?php echo translate('admin_news', 'News Management'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'characters' ? 'active' : ''; ?>" href="/Sahtout/admin/characters">
                        <i class="fas fa-user-edit me-2"></i> <?php echo translate('admin_characters', 'Character Management'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'shop' ? 'active' : ''; ?>" href="/Sahtout/admin/ashop">
                        <i class="fas fa-shopping-cart me-2"></i> <?php echo translate('admin_shop', 'Shop Management'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'gm_cmd' ? 'active' : ''; ?>" href="/Sahtout/admin/gm_cmd">
                        <i class="fas fa-terminal me-2"></i> <?php echo translate('admin_gm_commands', 'GM Commands'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page_class === 'settings' ? 'active' : ''; ?>" href="/Sahtout/admin/settings/general">
                        <i class="fas fa-cogs me-2"></i> <?php echo translate('admin_settings', 'Settings'); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="/Sahtout/logout">
                        <i class="fas fa-sign-out-alt me-2"></i> <?php echo translate('logout', 'Logout'); ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>

<style>
    /* Main sidebar card */
    .admin-sidebar-card {
        border: 2px solid #f1c40f;
        border-radius: 10px;
        box-shadow: 0 0 15px rgba(0,0,0,0.7);
        text-align: center;
        overflow: hidden;
    }
    
    /* Card header */
    .admin-sidebar-header {
        background: #2c2c2c;
        color: #ffffffff;
        font-family: 'UnifrakturCook', sans-serif;
        text-shadow: 0 0 5px #380766ff, 0 0 10px #0051ffff;
        padding: 1rem;
        font-weight: bold;
        font-size: 1.2rem;
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
    }
    
    .admin-sidebar-header h5 {
        flex-grow: 1;
        text-align: center;
        margin: 0;
    }
    
    .admin-sidebar-header .mobile-toggle {
        position: absolute;
        right: 1rem;
    }
    
    /* Navigation links */
    .admin-sidebar-nav .nav-link {
        color: #ccc;
        background: #1a1a1a;
        margin: 0.3rem 0;
        padding: 0.7rem 1rem;
        border-radius: 6px;
        font-weight: 500;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
    }
    
    .admin-sidebar-nav .nav-link:hover {
        background: #ffffff;
        color: #000000;
        transform: scale(1.01);
    }
    
    .admin-sidebar-nav .nav-link.active {
        background: #3302a5;
        color: #f8fcff;
        text-shadow: 0 0 8px #0b70ce, 0 0 12px #0f69f1;
        box-shadow: 0 0 10px #012974 inset;
    }
    
    .admin-sidebar-nav .nav-link i {
        width: 22px;
        text-align: center;
        font-size: 1.1rem;
    }
    
    /* Logout link */
    .admin-sidebar-nav .nav-link.text-danger {
        color: #dc3545 !important;
    }
    
    .admin-sidebar-nav .nav-link.text-danger:hover {
        background: #f8d7da;
        color: #c82333 !important;
    }
    
    /* Mobile toggle button */
    .mobile-toggle {
        display: none;
        background: none;
        border: none;
        color: #f1c40f;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.5rem;
    }
    
    /* Mobile menu */
    .admin-sidebar-menu {
        display: block;
        background: #2c2c2c; /* Default background for consistency */
        border-radius: 0 0 10px 10px;
    }
    
    /* Mobile adjustments */
    @media (max-width: 768px) {
        .admin-sidebar {
            position: relative;
            width: 100%; /* Full width on mobile */
            margin-left: 0;
            margin-right: 0;
        }
        
        .admin-sidebar-card {
            margin-bottom: 1rem;
            width: 100%; /* Ensure card takes full width */
        }
        
        .admin-sidebar-header {
            padding: 0.75rem;
            font-size: 1.1rem;
        }
        
        .admin-sidebar-nav .nav-link {
            padding: 0.6rem 0.8rem;
            font-size: 0.9rem;
        }
        
        .mobile-toggle {
            display: block;
        }
        
        .admin-sidebar-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background: linear-gradient(180deg, #333333, #1f1f1f); /* Distinct dropdown background */
            border: 1px solid #f1c40f; /* Border to frame the dropdown */
            border-top: none; /* Remove top border for seamless connection to header */
            padding: 0.5rem; /* Add padding around the menu */
            box-shadow: 0 4px 8px rgba(0,0,0,0.5); /* Subtle shadow for depth */
        }
        
        .admin-sidebar-menu.active {
            max-height: 600px; /* Adjust based on content height */
        }
        
        .mobile-toggle.active .fa-bars::before {
            content: "\f00d"; /* Change to 'X' icon when open */
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButton = document.querySelector('.mobile-toggle');
        const menu = document.querySelector('.admin-sidebar-menu');
        
        toggleButton.addEventListener('click', function() {
            menu.classList.toggle('active');
            toggleButton.classList.toggle('active');
        });
    });
</script>