<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Rakumiru - ランキング差分</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
  <div class="app">
    <header class="app-header">
      <div class="brand">
        <span class="brand__dot"></span>
        <div class="brand__text">
          <p class="brand__label">Rakumiru</p>
          <h1>ランキング差分</h1>
        </div>
      </div>
      <div class="header-actions">
        <div class="date-badge">直近 vs 前日</div>
        <button class="ghost-button" type="button">通知</button>
      </div>
    </header>
    <section class="selector">
      <div class="selector__title">
        <h2>ジャンルを選択</h2>
        <p>片手操作しやすいように上部で切替</p>
      </div>
      <div class="selector__controls">
        <label class="field">
          <span>親ジャンル</span>
          <select>
            <option>レディースファッション</option>
            <option>家電</option>
            <option>インテリア</option>
          </select>
        </label>
        <label class="field">
          <span>子ジャンル</span>
          <select>
            <option>ワンピース</option>
            <option>トップス</option>
            <option>シューズ</option>
          </select>
        </label>
      </div>
    </section>
    <main>