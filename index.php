<?php
require_once __DIR__ . '/header.php';
?>
<section class="section">
  <div class="section__header">
    <div>
      <h2>レディースファッション / ワンピース</h2>
      <p>最新データの差分をスマホで見やすく表示</p>
    </div>
    <button class="primary-button" type="button">更新</button>
  </div>
  <div class="card-list">
    <article class="card">
      <div class="card__rank">
        <span class="rank">1</span>
        <span class="delta up">▲2</span>
      </div>
      <div class="card__body">
        <h3>シンプルリネンワンピース</h3>
        <p class="card__meta">ショップ: Minimal Store</p>
        <div class="badge-group">
          <span class="badge sale">セール中</span>
          <span class="badge new">初ランクイン</span>
        </div>
        <div class="diff-grid">
          <div>
            <span>価格</span>
            <strong class="down">-¥1,200</strong>
          </div>
          <div>
            <span>レビュー</span>
            <strong class="up">+18</strong>
          </div>
          <div>
            <span>ポイント</span>
            <strong>+1%</strong>
          </div>
        </div>
      </div>
    </article>

    <article class="card">
      <div class="card__rank">
        <span class="rank">2</span>
        <span class="delta down">▼1</span>
      </div>
      <div class="card__body">
        <h3>フレアカットワンピース</h3>
        <p class="card__meta">ショップ: Urban Mood</p>
        <div class="badge-group">
          <span class="badge">セールなし</span>
        </div>
        <div class="diff-grid">
          <div>
            <span>価格</span>
            <strong class="up">+¥800</strong>
          </div>
          <div>
            <span>レビュー</span>
            <strong class="down">-3</strong>
          </div>
          <div>
            <span>ポイント</span>
            <strong>±0%</strong>
          </div>
        </div>
      </div>
    </article>

    <article class="card">
      <div class="card__rank">
        <span class="rank">3</span>
        <span class="delta neutral">=0</span>
      </div>
      <div class="card__body">
        <h3>シルキータッチワンピース</h3>
        <p class="card__meta">ショップ: Softline</p>
        <div class="badge-group">
          <span class="badge alert">ランク外の兆候</span>
        </div>
        <div class="diff-grid">
          <div>
            <span>価格</span>
            <strong class="neutral">±0</strong>
          </div>
          <div>
            <span>レビュー</span>
            <strong class="up">+6</strong>
          </div>
          <div>
            <span>ポイント</span>
            <strong class="down">-1%</strong>
          </div>
        </div>
      </div>
    </article>
  </div>
</section>

<section class="section muted">
  <div class="section__header">
    <div>
      <h2>次の注目ジャンル</h2>
      <p>4件目以降はスクロールで確認</p>
    </div>
  </div>
  <div class="scroll-row">
    <div class="scroll-card">
      <h3>シューズ</h3>
      <p>ランキング変動が活発なジャンル。</p>
      <button class="ghost-button" type="button">ジャンルを見る</button>
    </div>
    <div class="scroll-card">
      <h3>バッグ</h3>
      <p>セール多めの注目ジャンル。</p>
      <button class="ghost-button" type="button">ジャンルを見る</button>
    </div>
    <div class="scroll-card">
      <h3>アクセサリー</h3>
      <p>レビュー増加が目立つカテゴリ。</p>
      <button class="ghost-button" type="button">ジャンルを見る</button>
    </div>
  </div>
</section>
<?php
require_once __DIR__ . '/footer.php';
?>