<?php
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RAKUMiRU-ADMIN</title>
  <!-- Tab icon (favicon) -->
  <!-- <link rel="icon" href="../img/icon5.png" type="image/png">
  <link rel="apple-touch-icon" href="../img/icon5.png"> -->

  <!-- <link rel="stylesheet" href="css/style.css"> -->
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
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
          <img src="../img/logo.png" alt="RAKUMiRU" class="brand__logo">
        </a>
      </div>
<!-- 
        <div class="app-header__actions">
          <button class="header-icon-button" type="button" id="api-key-open" aria-haspopup="dialog" aria-controls="api-key-modal">
            <img src="img/api.png" alt="Gemini APIキー設定" />
          </button>
        </div> -->
      </div>
      <!-- <p class="brand__subtitle">ランキング変動をスマホで見やすく</p> -->
    </div>
  </header>
  <main class="container">