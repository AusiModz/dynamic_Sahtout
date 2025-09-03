<?php
if (!defined('ALLOWED_ACCESS')) {
    require_once dirname(__DIR__) . '/languages/language.php';
    header('HTTP/1.1 403 Forbidden');
    exit(translate('error_direct_access', 'Direct access to this file is not allowed.'));
}

require_once dirname(__DIR__) . '/includes/config.settings.php';
?>
<link rel="stylesheet" href="/sahtout/assets/css/footer.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<footer>
  <div class="footer-container">
    <!-- Logo -->
    <div class="footer-logo">
      <a href="/sahtout"><img src="/sahtout/<?php echo $site_logo; ?>" alt="<?php echo translate('footer_logo_alt', 'Sahtout Server Logo'); ?>" class="footer-logo-img"></a> 
    </div>

    <!-- Copyright -->
    <div class="footer-center">
      <p>© <?php echo date('Y'); ?> Sahtout Server by Blody. All rights reserved.</p>
    </div>

    <!-- Socials -->
    <div class="footer-socials">
      <?php if (!empty($social_links['facebook'])): ?>
        <a href="<?php echo $social_links['facebook']; ?>" target="_blank" aria-label="<?php echo translate('facebook_alt', 'Facebook'); ?>">
          <i class="fab fa-facebook-f"></i>
        </a>
      <?php endif; ?>
      <?php if (!empty($social_links['twitter'])): ?>
        <a href="<?php echo $social_links['twitter']; ?>" target="_blank" aria-label="<?php echo translate('twitter_alt', 'Twitter (X)'); ?>">
          <i class="fab fa-x-twitter"></i>
        </a>
      <?php endif; ?>
      <?php if (!empty($social_links['tiktok'])): ?>
        <a href="<?php echo $social_links['tiktok']; ?>" target="_blank" aria-label="<?php echo translate('tiktok_alt', 'TikTok'); ?>">
          <i class="fab fa-tiktok"></i>
        </a>
      <?php endif; ?>
      <?php if (!empty($social_links['youtube'])): ?>
        <a href="<?php echo $social_links['youtube']; ?>" target="_blank" aria-label="<?php echo translate('youtube_alt', 'YouTube'); ?>">
          <i class="fab fa-youtube"></i>
        </a>
      <?php endif; ?>
      <?php if (!empty($social_links['discord'])): ?>
        <a href="<?php echo $social_links['discord']; ?>" target="_blank" aria-label="<?php echo translate('discord_alt', 'Discord'); ?>">
          <i class="fab fa-discord"></i>
        </a>
      <?php endif; ?>
      <?php if (!empty($social_links['twitch'])): ?>
        <a href="<?php echo $social_links['twitch']; ?>" target="_blank" aria-label="<?php echo translate('twitch_alt', 'Twitch'); ?>">
          <i class="fab fa-twitch"></i>
        </a>
      <?php endif; ?>
      <?php if (!empty($social_links['kick'])): ?>
        <a href="<?php echo $social_links['kick']; ?>" target="_blank" aria-label="<?php echo translate('kick_alt', 'Kick'); ?>">
          <img src="/sahtout/img/icons/kick-logo.png" alt="<?php echo translate('kick_alt', 'Kick'); ?>" class="kick-icon">
        </a>
      <?php endif; ?>
      <?php if (!empty($social_links['instagram'])): ?>
        <a href="<?php echo $social_links['instagram']; ?>" target="_blank" aria-label="<?php echo translate('instagram_alt', 'Instagram'); ?>">
          <i class="fab fa-instagram"></i>
        </a>
      <?php endif; ?>
      <?php if (!empty($social_links['github'])): ?>
        <a href="<?php echo $social_links['github']; ?>" target="_blank" aria-label="<?php echo translate('github_alt', 'GitHub'); ?>">
          <i class="fab fa-github"></i>
        </a>
      <?php endif; ?>
      <?php if (!empty($social_links['linkedin'])): ?>
        <a href="<?php echo $social_links['linkedin']; ?>" target="_blank" aria-label="<?php echo translate('linkedin_alt', 'LinkedIn'); ?>">
          <i class="fab fa-linkedin-in"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Back to Top Button -->
  <button id="backToTop" title="<?php echo translate('back_to_top', 'Back to Top'); ?>">
    <i class="fas fa-arrow-up"></i>
  </button>
</footer>

<!-- Back to Top Script -->
<script>
  const backToTop = document.getElementById("backToTop");

  window.addEventListener("scroll", () => {
    backToTop.style.opacity = window.scrollY > 300 ? "1" : "0";
    backToTop.style.pointerEvents = window.scrollY > 300 ? "auto" : "none";
    backToTop.style.transform = window.scrollY > 300 ? "translateY(0)" : "translateY(20px)";
  });

  backToTop.addEventListener("click", () => {
    backToTop.style.transform = "scale(0.9)";
    setTimeout(() => {
      backToTop.style.transform = "scale(1)";
    }, 100);
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
</script>