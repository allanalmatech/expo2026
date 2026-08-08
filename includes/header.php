<?php
declare(strict_types=1);

require_once __DIR__ . '/csrf.php';

$pageTitle = $pageTitle ?? APP_EVENT_NAME;
$bodyClass = $bodyClass ?? '';
$extraCss = $extraCss ?? [];
$extraHead = $extraHead ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo h(csrf_token()); ?>">
    <title><?php echo h($pageTitle . ' | ' . APP_EVENT_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo h(app_url('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(app_url('assets/css/responsive.css')); ?>">
    <?php foreach ($extraCss as $cssPath): ?>
        <link rel="stylesheet" href="<?php echo h(app_url((string) $cssPath)); ?>">
    <?php endforeach; ?>
    <?php echo $extraHead; ?>
</head>
<body class="<?php echo h($bodyClass); ?>">
<div class="toast-stack" id="toast-stack">
    <?php foreach (get_flash_messages() as $flash): ?>
        <div class="toast toast-<?php echo h($flash['type']); ?>" data-toast><?php echo h($flash['message']); ?></div>
    <?php endforeach; ?>
</div>
