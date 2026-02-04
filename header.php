<?php
$showAuthenticatedActions = !empty($_SESSION['line_user_id']) && !empty($_SESSION['password_authenticated']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RAKUMiRU</title>

  <!-- Tab icon (favicon) -->
  <!-- <link rel="icon" href="img/icon4.png" type="image/png">
  <link rel="apple-touch-icon" href="img/icon4.png"> -->

  <!-- <link rel="stylesheet" href="css/style.css"> -->
  <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
  <?php if (!empty($additionalStyles) && is_array($additionalStyles)): ?>
    <?php foreach ($additionalStyles as $styleUrl): ?>
      <link rel="stylesheet" href="<?= htmlspecialchars($styleUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<?php
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$basePath = $basePath === '' ? '/' : $basePath . '/';
?>
<body data-api-base="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>">
  <header class="app-header">
    <div class="container">
      <div class="app-header__inner">
      <div class="brand">
        <a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>" class="brand__link" aria-label="RAKUMiRU">
          <img src="img/logo.png" alt="RAKUMiRU" class="brand__logo">
        </a>
      </div>

        <div class="app-header__actions">
          
          <button class="header-icon-button" type="button" id="tutorial-start">チュートリアル</button>
        </div>
      </div>
      <!-- <p class="brand__subtitle">ランキング変動をスマホで見やすく</p> -->
    </div>
  </header>
  <main class="container">