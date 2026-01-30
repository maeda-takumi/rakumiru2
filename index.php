<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
session_start();

function renderLineOnlyMessage(): void {
  http_response_code(403);
  echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>LINEログイン</title></head><body>';
  echo '<p>専用LINEからログインしてください</p>';
  echo '</body></html>';
  exit;
}

if (empty($_SESSION['line_user_id'])) {
  renderLineOnlyMessage();
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$error = null;
$pdo = null;
$userId = null;
$itemDescriptions = [];

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
$dropouts = [];

if ($pdo) {
  $stmt = $pdo->prepare('SELECT id FROM users WHERE line_user_id = :line_user_id LIMIT 1');
  $stmt->execute(['line_user_id' => $_SESSION['line_user_id']]);
  $userId = $stmt->fetchColumn();

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
         LIMIT 30"
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


      if ($previousDate && $previousMap) {
        $currentCodes = array_column($rankings, 'item_code');
        foreach ($previousMap as $code => $row) {
          if (!in_array($code, $currentCodes, true)) {
            $dropouts[] = $row;
          }
        }
      }

      if ($userId && $rankings) {
        $placeholders = [];
        $params = ['user_id' => $userId];
        foreach ($rankings as $index => $row) {
          $placeholder = ':item' . $index;
          $placeholders[] = $placeholder;
          $params['item' . $index] = $row['item_code'];
        }

        $sql = sprintf(
          'SELECT item_code, description FROM item_descriptions WHERE user_id = :user_id AND item_code IN (%s) ORDER BY updated_at DESC',
          implode(', ', $placeholders)
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
          if (!isset($itemDescriptions[$row['item_code']])) {
            $itemDescriptions[$row['item_code']] = $row['description'];
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

    <div class="ranking-list">
      <?php foreach ($rankings as $row):
        $prev = $previousMap[$row['item_code']] ?? null;
        $rankChange = $prev ? formatRankChange((int) $row['rank_pos'], (int) $prev['rank_pos']) : 'NEW';
        $priceDiff = $prev ? formatDiff((int) $row['price'], (int) $prev['price']) : '—';
        $reviewDiff = $prev ? formatDiff((int) $row['review_count'], (int) $prev['review_count']) : '—';
        $onSale = isOnSale($row['sale_start_at'], $row['sale_end_at']);
        $description = $itemDescriptions[$row['item_code']] ?? null;
      ?>
        <article class="rank-card" data-item-code="<?= htmlspecialchars($row['item_code'], ENT_QUOTES, 'UTF-8') ?>">
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
              <a class="rank-card__title" href="<?= htmlspecialchars($row['item_url'] ?? '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars($row['item_name'] ?? '商品名未登録', ENT_QUOTES, 'UTF-8') ?>">
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
          <div class="rank-card__footer">
            <div class="rank-card__description" data-description="<?= htmlspecialchars($description ?? '', ENT_QUOTES, 'UTF-8') ?>">
              <?php if ($description): ?>
                <p><?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?></p>
              <?php else: ?>
                <p class="rank-card__description--empty">商品説明を入力してください</p>
              <?php endif; ?>
            </div>
            <div class="rank-card__actions">
              <button class="rank-card__button" type="button" aria-label="商品説明を入力" data-action="edit-description">
                <img src="img/input.png" alt="" />
              </button>
              <button class="rank-card__button" type="button" aria-label="AI説明を生成" data-action="ai-description">
                <img src="img/ai.png" alt="" />
              </button>
              <button class="rank-card__button" type="button" aria-label="説明をコピー" data-action="copy-description">
                <img src="img/copy.png" alt="" />
              </button>              
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
<div class="modal" id="description-modal" aria-hidden="true">
  <div class="modal__overlay" data-modal-close></div>
  <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="description-modal-title">
    <div class="modal__header">
      <h3 id="description-modal-title">商品説明を編集</h3>
      <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
    </div>
    <textarea class="modal__textarea" id="description-modal-text" rows="6" placeholder="商品説明を入力してください"></textarea>
    <div class="modal__actions">
      <button type="button" class="modal__button modal__button--ghost" data-modal-close>キャンセル</button>
      <button type="button" class="modal__button" id="description-modal-save">保存</button>
    </div>
    <p class="modal__status" id="description-modal-status" aria-live="polite"></p>
  </div>
</div>
<?php
include __DIR__ . '/footer.php';