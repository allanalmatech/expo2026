<?php
declare(strict_types=1);
?>
<header class="public-header">
    <a class="brand" href="<?php echo h(app_url('public/index.php')); ?>"><?php echo h(APP_EVENT_NAME); ?></a>
    <button class="nav-toggle" type="button" data-public-nav-toggle aria-label="Open menu">Menu</button>
    <nav class="public-links" id="public-links">
        <a href="<?php echo h(app_url('public/index.php#marketplace')); ?>">Marketplace</a>
        <a href="<?php echo h(app_url('public/index.php#guidelines')); ?>">Guidelines</a>
        <a href="<?php echo h(app_url('public/index.php#support')); ?>">Support</a>
        <a class="button button-primary" href="<?php echo h(app_url('public/create-account.php')); ?>">Create Portal Account</a>
        <a href="<?php echo h(app_url('public/login.php')); ?>">Login</a>
    </nav>
</header>
