<?php
declare(strict_types=1);
?>
<footer class="site-footer">
    <div>
        <strong><?php echo h(APP_EVENT_NAME); ?></strong>
        <p>&copy; <?php echo date('Y'); ?> Freshers Expo Committee. All rights reserved.</p>
    </div>
    <nav aria-label="Footer links">
        <a href="<?php echo h(app_url('public/index.php#support')); ?>">Contact Support</a>
        <a href="<?php echo h(app_url('public/index.php#guidelines')); ?>">Guidelines</a>
    </nav>
</footer>
<script src="<?php echo h(app_url('assets/js/main.js')); ?>" defer></script>
<script src="<?php echo h(app_url('assets/js/ajax.js')); ?>" defer></script>
<?php foreach (($extraJs ?? []) as $jsPath): ?>
    <script src="<?php echo h(app_url((string) $jsPath)); ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
