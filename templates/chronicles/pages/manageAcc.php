<?php
defined('MYAAC') or die('Direct access not allowed!');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo template_place_holder('head_start'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Account - <?php echo config('server_name'); ?></title>
    <link rel="stylesheet" href="<?php echo $template_path; ?>/css/style.css">
    <?php echo template_place_holder('head_end'); ?>
</head>
<body class="manage_acc">

   <?php echo chronicles_header(); ?>

    <main class="register_main">
        <section class="container">
            <?php echo $content; ?>
        </section>
    </main>

    <?php echo chronicles_footer(); ?>
    <?php echo template_place_holder('body_end'); ?>

    <script src="<?php echo $template_path; ?>/js/script.js"></script>
</body>
</html>