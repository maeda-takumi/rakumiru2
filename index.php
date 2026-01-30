<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$error = null;
$pdo = null;

try {
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  $error = 'データベースに接続できませんでした。';
}

$selectedParent = filter_input(INPUT_GET, 'parent_id', FILTER_VALIDATE_INT);
$selectedGenre = filter_input(INPUT_GET, 'genre_id', FILTER_VALIDATE_INT);

$parents = [];
$children = [];
$latestDate = null;
$previousDate = null;
$rankings = [];
$previousMap = [];
$newEntry = null;
$dropouts = [];

if ($pdo) {
  $parents = $pdo->query("SELECT genre_id, genre_name FROM genres WHERE depth = 0 AND is_active = 1 ORDER BY genre_name")->fetchAll();

  if ($selectedParent) {
    $stmt = $pdo->prepare("SELECT genre_id, genre_name FROM genres WHERE depth = 1 AND parent_genre_id = :parent AND is_active = 1 ORDER BY genre_name");
    $stmt->execute(['parent' => $selectedParent]);
    $children = $stmt->fetchAll();
  }

  if ($selectedGenre) {
    $stmt = $pdo->prepare("SELECT MAX(captured_date) AS latest_date FROM rank_daily WHERE genre_id = :genre");
    $stmt->execute(['genre' => $selectedGenre]);
    $latestDate = $stmt->fetchColumn();

    if ($latestDate) {
      $stmt = $pdo->prepare("SELECT MAX(captured_date) AS prev_date FROM rank_daily WHERE genre_id = :genre AND captured_date < :latest");
      $stmt->execute(['genre' => $selectedGenre, 'latest' => $latestDate]);
      $previousDate = $stmt->fetchColumn();

      $stmt = $pdo->prepare(
        "SELECT rd.rank_pos, rd.item_code, rd.price, rd.review_count, rd.point_rate, rd.sale_start_at, rd.sale_end_at,
                i.item_name, i.item_url, i.image_url, i.shop_name
         FROM rank_daily rd
         JOIN items i ON rd.item_code = i.item_code
         WHERE rd.genre_id = :genre AND rd.captured_date = :latest
         ORDER BY rd.rank_pos ASC
         LIMIT 10"
      );
      $stmt->execute(['genre' => $selectedGenre, 'latest' => $latestDate]);
      $rankings = $stmt->fetchAll();

      if ($previousDate) {
        $stmt = $pdo->prepare(
          "SELECT item_code, rank_pos, price, review_count
           FROM rank_daily
           WHERE genre_id = :genre AND captured_date = :prev"
        );
        $stmt->execute(['genre' => $selectedGenre, 'prev' => $previousDate]);
        foreach ($stmt->fetchAll() as $row) {
          $previousMap[$row['item_code']] = $row;
        }
      }

      foreach ($rankings as $row) {
        if (!isset($previousMap[$row['item_code']])) {
          $newEntry = $row;
          break;
        }
      }

      if ($previousDate && $previousMap) {
        $currentCodes = array_column($rankings, 'item_code');
        foreach ($previousMap as $code => $row) {
          if (!in_array($code, $currentCodes, true)) {
            $dropouts[] = $row;
          }
        }
      }
    }
  }
}

function formatDiff(?int $current, ?int $previous): string {
  if ($current === null || $previous === null) {
    return '—';
  }
  $diff = $current - $previous;
  if ($diff === 0) {
    return '±0';
  }
  return ($diff > 0 ? '+' : '') . number_format($diff);
}

function formatRankChange(?int $current, ?int $previous): string {
  if ($current === null || $previous === null) {
    return 'NEW';
  }
  $diff = $previous - $current;
  if ($diff === 0) {
    return '±0';
  }
  return ($diff > 0 ? '↑' : '↓') . abs($diff);
}

function isOnSale(?string $start, ?string $end): bool {
  if (!$start || !$end) {
    return false;
  }
  $now = new DateTime('now');
  return $now >= new DateTime($start) && $now <= new DateTime($end);
}

include __DIR__ . '/header.php';
?>

<section class="panel">
  <h1>ジャンルを選択</h1>
  <?php if ($error): ?>
    <p class="notice notice--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
  <form class="genre-form" method="get">
    <label>
      親ジャンル
      <select name="parent_id" id="parent-select">
        <option value="">選択してください</option>
        <?php foreach ($parents as $parent): ?>
          <option value="<?= (int) $parent['genre_id'] ?>" <?= $selectedParent === (int) $parent['genre_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($parent['genre_name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      子ジャンル
      <select name="genre_id" id="child-select" <?= $selectedParent ? '' : 'disabled' ?>>
        <option value="">選択してください</option>
        <?php foreach ($children as $child): ?>
          <option value="<?= (int) $child['genre_id'] ?>" <?= $selectedGenre === (int) $child['genre_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($child['genre_name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>
</section>

<section class="panel">
  <div class="panel__header">
    <h2>差分ランキング</h2>
    <?php if ($latestDate): ?>
      <div class="panel__meta">
        <span>最新: <?= htmlspecialchars($latestDate, ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($previousDate): ?>
          <span>比較: <?= htmlspecialchars($previousDate, ENT_QUOTES, 'UTF-8') ?></span>
        <?php else: ?>
          <span>比較データなし</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$selectedGenre): ?>
    <p class="notice">ジャンルを選ぶとランキング差分が表示されます。</p>
  <?php elseif (!$latestDate): ?>
    <p class="notice">このジャンルのランキングデータがまだありません。</p>
  <?php else: ?>
    <?php if ($newEntry): ?>
      <div class="callout">
        <span class="callout__title">初ランクイン</span>
        <span class="callout__text"><?= htmlspecialchars($newEntry['item_name'], ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    <?php endif; ?>

    <div class="ranking-list">
      <?php foreach ($rankings as $row):
        $prev = $previousMap[$row['item_code']] ?? null;
        $rankChange = $prev ? formatRankChange((int) $row['rank_pos'], (int) $prev['rank_pos']) : 'NEW';
        $priceDiff = $prev ? formatDiff((int) $row['price'], (int) $prev['price']) : '—';
        $reviewDiff = $prev ? formatDiff((int) $row['review_count'], (int) $prev['review_count']) : '—';
        $onSale = isOnSale($row['sale_start_at'], $row['sale_end_at']);
      ?>
        <article class="rank-card">
          <div class="rank-card__rank">#<?= (int) $row['rank_pos'] ?></div>
          <div class="rank-card__body">
            <div class="rank-card__media">
              <?php if (!empty($row['image_url'])): ?>
                <img src="<?= htmlspecialchars($row['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($row['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
              <?php else: ?>
                <div class="rank-card__placeholder">No Image</div>
              <?php endif; ?>
            </div>
            <div class="rank-card__info">
              <a class="rank-card__title" href="<?= htmlspecialchars($row['item_url'] ?? '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                <?= htmlspecialchars($row['item_name'] ?? '商品名未登録', ENT_QUOTES, 'UTF-8') ?>
              </a>
              <p class="rank-card__shop"><?= htmlspecialchars($row['shop_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
              <div class="rank-card__meta">
                <span class="tag <?= $onSale ? 'tag--sale' : '' ?>">
                  <?= $onSale ? 'セール中' : '通常' ?>
                </span>
                <span class="tag">ポイント <?= (int) ($row['point_rate'] ?? 0) ?>%</span>
              </div>
            </div>
          </div>
          <div class="rank-card__stats">
            <div>
              <span class="stat__label">ランク変動</span>
              <span class="stat__value <?= strpos($rankChange, '↑') !== false ? 'stat__value--up' : (strpos($rankChange, '↓') !== false ? 'stat__value--down' : '') ?>">
                <?= htmlspecialchars($rankChange, ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
            <div>
              <span class="stat__label">価格</span>
              <span class="stat__value">¥<?= number_format((int) ($row['price'] ?? 0)) ?></span>
              <span class="stat__diff"><?= htmlspecialchars($priceDiff, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div>
              <span class="stat__label">レビュー</span>
              <span class="stat__value"><?= number_format((int) ($row['review_count'] ?? 0)) ?></span>
              <span class="stat__diff"><?= htmlspecialchars($reviewDiff, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($previousDate && $dropouts): ?>
      <div class="dropout">
        <h3>ランク外になった商品</h3>
        <ul>
          <?php foreach ($dropouts as $drop): ?>
            <li>前日 #<?= (int) $drop['rank_pos'] ?> / <?= htmlspecialchars($drop['item_code'], ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php
include __DIR__ . '/footer.php';