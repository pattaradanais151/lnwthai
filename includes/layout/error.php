<?php
/** Path: includes/layout/error.php — ห้ามเรียก DB ในไฟล์นี้ */
defined('LNW_ROOT') || exit;
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= (int) $code ?> · <?= e($title) ?> · Lnwthai</title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
<script src="<?= e(asset('js/theme-init.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<main class="error-page">
  <div class="error-card">
    <p class="error-code"><?= (int) $code ?></p>
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <a class="btn btn-primary" href="<?= e(url('')) ?>">กลับหน้าแรก</a>
  </div>
</main>
</body>
</html>