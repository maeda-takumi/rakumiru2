<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rakumiru | Genres</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
</head>
<body>
<header class="topbar">
  <div class="topbar__inner">
    <div class="brand">
      <span class="brand__dot"></span>
      <span class="brand__name">Rakumiru</span>
      <span class="brand__sub">Genres Viewer</span>
    </div>

    <div class="topbar__right">
      <div class="pill" id="js-job-pill">job: loading…</div>
    </div>
  </div>
</header>

<main class="app">
