<?php
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RAKUMiRU</title>
  <!-- <link rel="stylesheet" href="css/style.css"> -->
  <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
</head>
<?php
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$basePath = $basePath === '' ? '/' : $basePath . '/';
?>
<body data-api-base="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>"
      data-ai-remaining="<?= isset($aiRemaining) ? htmlspecialchars((string) $aiRemaining, ENT_QUOTES, 'UTF-8') : '' ?>"
      data-ai-daily-limit="<?= isset($aiDailyLimit) ? htmlspecialchars((string) $aiDailyLimit, ENT_QUOTES, 'UTF-8') : '' ?>">
  <header class="app-header">
    <div class="container">
      <div class="brand">
        <span class="brand__dot"></span>
        <span class="brand__title">RAKUMiRU</span>
      </div>
      <!-- <p class="brand__subtitle">ランキング変動をスマホで見やすく</p> -->
    </div>
  </header>
  <main class="container">