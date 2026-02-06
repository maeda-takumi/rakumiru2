<?php
$showAuthenticatedActions = !empty($_SESSION['line_user_id']) && !empty($_SESSION['password_authenticated']);
$showTutorialButton = basename($_SERVER['SCRIPT_NAME']) === 'index.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RAKUMiRU</title>

  <!-- Tab icon (favicon) -->
  <link rel="icon" href="img/tab_icon.png" type="image/png">
  <link rel="apple-touch-icon" href="img/tab_icon.png">

  <!-- <link rel="stylesheet" href="css/style.css"> -->
  <link rel="stylesheet" href="css/auth.css?v=<?= time() ?>">
  <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">

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
          <?php if ($showTutorialButton): ?>
            <!-- <button class="header-icon-button" type="button" id="tutorial-start">チュートリアル</button> -->
          <?php endif; ?>
          <button
            class="header-menu-button"
            type="button"
            id="menu-toggle"
            aria-label="メニューを開く"
            aria-controls="global-drawer"
            aria-expanded="false"
          >
            <span></span>
            <span></span>
            <span></span>
          </button>
        </div>
      </div>
      <!-- <p class="brand__subtitle">ランキング変動をスマホで見やすく</p> -->
    </div>
  </header>
  <div class="global-drawer" id="global-drawer" aria-hidden="true">
    <button class="global-drawer__backdrop" type="button" data-drawer-close aria-label="メニューを閉じる"></button>
    <aside class="global-drawer__panel" role="dialog" aria-modal="true" aria-label="メニュー">
      <div class="global-drawer__header">
        <p class="global-drawer__title">メニュー</p>
        <button class="global-drawer__close" type="button" data-drawer-close aria-label="閉じる">×</button>
      </div>
      <div class="global-drawer__list">
        <?php if ($showTutorialButton): ?>
          <button class="header-icon-button global-drawer__item" type="button" id="tutorial-start">チュートリアル</button>
          <button class="global-drawer__item" type="button" data-modal-target="terms">利用規約</button>
          <button class="global-drawer__item" type="button" data-modal-target="privacy">プライバシーポリシー</button>
          <button class="global-drawer__item" type="button" data-modal-target="mode">AIモード設定</button>
        <?php endif; ?>
      </div>
    </aside>
  </div>
  <main class="container">