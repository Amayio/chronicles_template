<?php
defined('MYAAC') or die('Direct access not allowed!');

$page = defined('PAGE') ? PAGE : 'home';

$standalone_pages = [
    'account/create'            => 'register.php',
    // 'account/manage'            => 'manageAcc.php',
    // 'account/change-email'      => 'manageAcc.php',
    // 'account/register'          => 'manageAcc.php',
    // 'account/change-info'       => 'manageAcc.php',
    // 'account/characters/create' => 'manageAcc.php',
    // 'account/characters/delete' => 'manageAcc.php',
    // 'points'                    => 'shop.php',
    'items'                      => 'items.php'
];

if (isset($standalone_pages[$page])) {
    include __DIR__ . '/pages/' . $standalone_pages[$page];
    exit;
}
?>

<!DOCTYPE html>
<html class="scroll-lock" lang="en">
<head>
    <?php echo template_place_holder('head_start'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo config('server_name'); ?> - Home</title>
    <link rel="stylesheet" href="<?php echo $template_path; ?>/css/style.css">
    <?php echo template_place_holder('head_end'); ?>
</head>

<body class="home .scroll-lock">

 <?php echo chronicles_header(); ?>

<main>

<section class="hero-section">
 
<video autoplay muted loop playsinline class="background-video">
    <source src="<?php echo $template_path; ?>/media/chronicles_background.webm" type="video/webm">
  </video>

  <div class="hero-content">
    <div class="hero-text">
    <h1 class="hero__title">Shinobi Chronicles</h1>
    <p class="hero__subtitle">Relive the saga, face the aberrations, and survive a reality that’s breaking apart.</p>
 </div>
    
 <div class="cta">
        <a href="<?php echo getLink('account/create'); ?>" class="cta-button">Begin Shinobi Adventure</a>
        <a href="<?php echo getLink('wiki'); ?>" class="learn-game-btn">Game Guide</a>
    </div>
   
 
</section>

</main>


 <?php echo chronicles_footer(); ?>

 <?php echo template_place_holder('body_end'); ?>
</body>
</html>