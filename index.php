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

$selectedGenreIds = filter_input(INPUT_GET, 'genre_ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
$selectedGenreIds = is_array($selectedGenreIds) ? $selectedGenreIds : [];
$selectedGenreIds = array_values(array_unique(array_filter(array_map('intval', $selectedGenreIds))));

$selectedGenre = filter_input(INPUT_GET, 'genre_id', FILTER_VALIDATE_INT);
$filterSaleOnly = filter_input(INPUT_GET, 'sale_only') === '1';
$filterNewOnly = filter_input(INPUT_GET, 'new_only') === '1';
$filterDropoutOnly = filter_input(INPUT_GET, 'dropout_only') === '1';

$genres = [];
$genreMap = [];
$selectedGenreNames = [];
$latestDate = null;
$previousDate = null;
$rankings = [];
$previousMap = [];
$dropouts = [];
$genreData = [];

if ($pdo) {
  $stmt = $pdo->prepare('SELECT id FROM users WHERE line_user_id = :line_user_id LIMIT 1');
  $stmt->execute(['line_user_id' => $_SESSION['line_user_id']]);
  $userId = $stmt->fetchColumn();

  $genres = $pdo->query("SELECT genre_id, genre_name FROM genres WHERE depth = 0 AND is_active = 1 ORDER BY genre_name")->fetchAll();

  foreach ($genres as $genre) {
    $genreMap[(int) $genre['genre_id']] = $genre['genre_name'];
  }

  $selectedGenreIds = array_values(array_filter(
    $selectedGenreIds,
    fn (int $genreId): bool => isset($genreMap[$genreId])
  ));
  foreach ($selectedGenreIds as $genreId) {
    $selectedGenreNames[] = $genreMap[$genreId];
  }

  foreach ($selectedGenreIds as $genreId) {
    $latestDate = null;
    $previousDate = null;
    $rankings = [];
    $previousMap = [];
    $dropouts = [];
    $itemDescriptions = [];
    
    $stmt = $pdo->prepare("SELECT MAX(captured_date) AS latest_date FROM rank_daily WHERE genre_id = :genre");
    $stmt->execute(['genre' => $genreId]);
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
      $stmt->execute(['genre' => $genreId, 'latest' => $latestDate]);
      $rankings = $stmt->fetchAll();

      if ($previousDate) {
        $stmt = $pdo->prepare(
          "SELECT item_code, rank_pos, price, review_count
           FROM rank_daily
           WHERE genre_id = :genre AND captured_date = :prev"
        );
        $stmt->execute(['genre' => $genreId, 'prev' => $previousDate]);
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

    $displayRankings = $rankings;
    if ($filterSaleOnly || $filterNewOnly) {
      $displayRankings = array_values(array_filter(
        $displayRankings,
        function (array $row) use ($filterSaleOnly, $filterNewOnly, $previousMap): bool {
          if ($filterSaleOnly && !isOnSale($row['sale_start_at'], $row['sale_end_at'])) {
            return false;
          }
          if ($filterNewOnly && isset($previousMap[$row['item_code']])) {
            return false;
          }
          return true;
        }
      ));
    }
    if ($filterDropoutOnly) {
      $displayRankings = [];
    }
    $genreData[] = [
      'genre_id' => $genreId,
      'genre_name' => $genreMap[$genreId],
      'latest_date' => $latestDate,
      'previous_date' => $previousDate,
      'rankings' => $rankings,
      'previous_map' => $previousMap,
      'dropouts' => $dropouts,
      'display_rankings' => $displayRankings,
      'item_descriptions' => $itemDescriptions,
    ];
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
  <div class="panel__header">
    <h2>差分ランキング</h2>
    <button class="settings-button" type="button" id="settings-open" aria-haspopup="dialog" aria-controls="settings-modal">
      <img src="img/option.png" alt="" />
      <span>設定</span>
    </button>
  </div>
  <?php if (!$selectedGenreIds): ?>
    <p class="notice">設定からジャンルを選ぶとランキング差分が表示されます。</p>
  <?php else: ?>
    <?php foreach ($genreData as $genre): ?>
      <div class="genre-section">
        <div class="genre-section__header">
          <h3 class="genre-section__title"><?= htmlspecialchars($genre['genre_name'], ENT_QUOTES, 'UTF-8') ?></h3>
          <?php if ($genre['latest_date']): ?>
            <div class="panel__meta">
              <span>最新: <?= htmlspecialchars($genre['latest_date'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php if ($genre['previous_date']): ?>
                <span>比較: <?= htmlspecialchars($genre['previous_date'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php else: ?>
                <span>比較データなし</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!$genre['latest_date']): ?>
          <p class="notice">このジャンルのランキングデータがまだありません。</p>
        <?php elseif ($filterDropoutOnly && !$genre['dropouts']): ?>
          <p class="notice">ランク外落ち商品がありません。</p>
        <?php elseif (!$genre['display_rankings'] && !$filterDropoutOnly): ?>
          <p class="notice">条件に合う商品がありません。</p>
        <?php else: ?>
          <?php if ($genre['display_rankings']): ?>
            <div class="ranking-list">
              <?php foreach ($genre['display_rankings'] as $row):
                $prev = $genre['previous_map'][$row['item_code']] ?? null;
                $rankChange = $prev ? formatRankChange((int) $row['rank_pos'], (int) $prev['rank_pos']) : 'NEW';
                $priceDiff = $prev ? formatDiff((int) $row['price'], (int) $prev['price']) : '—';
                $reviewDiff = $prev ? formatDiff((int) $row['review_count'], (int) $prev['review_count']) : '—';
                $onSale = isOnSale($row['sale_start_at'], $row['sale_end_at']);
                $description = $genre['item_descriptions'][$row['item_code']] ?? null;
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
                      <button class="rank-card__button" type="button" aria-label="AI説明を生成" data-action="ai-description">
                        <img src="img/ai.png" alt="" />
                      </button>
                      <button class="rank-card__button" type="button" aria-label="商品説明を入力" data-action="edit-description">
                        <img src="img/input.png" alt="" />
                      </button>
                      <button class="rank-card__button" type="button" aria-label="説明をコピー" data-action="copy-description">
                        <img src="img/copy.png" alt="" />
                      </button>              
                    </div>
                  </div>            
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($genre['previous_date'] && $genre['dropouts']): ?>
            <div class="dropout">
              <h3>ランク外になった商品</h3>
              <ul>
                <?php foreach ($genre['dropouts'] as $drop): ?>
                  <li>前日 #<?= (int) $drop['rank_pos'] ?> / <?= htmlspecialchars($drop['item_code'], ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<div class="modal" id="settings-modal" aria-hidden="true">
  <div class="modal__overlay" data-settings-close></div>
  <div class="modal__panel modal__panel--settings" role="dialog" aria-modal="true" aria-labelledby="settings-modal-title">
    <div class="modal__header">
      <h3 id="settings-modal-title">表示設定</h3>
      <button type="button" class="modal__close" data-settings-close aria-label="閉じる">×</button>
    </div>
    <form class="settings-form" method="get">
      <div class="settings-form__group">
        <span class="settings-form__label">ジャンル</span>
        <div class="settings-form__options">
          <?php foreach ($genres as $genre): ?>
            <?php $genreId = (int) $genre['genre_id']; ?>
            <label class="settings-form__checkbox">
              <input type="checkbox" name="genre_ids[]" value="<?= $genreId ?>" <?= in_array($genreId, $selectedGenreIds, true) ? 'checked' : '' ?>>
              <?= htmlspecialchars($genre['genre_name'], ENT_QUOTES, 'UTF-8') ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="settings-form__group">
        <span class="settings-form__label">絞り込み</span>
        <label class="settings-form__checkbox">
          <input type="checkbox" name="sale_only" value="1" <?= $filterSaleOnly ? 'checked' : '' ?>>
          セール中のみ
        </label>
        <label class="settings-form__checkbox">
          <input type="checkbox" name="new_only" value="1" <?= $filterNewOnly ? 'checked' : '' ?>>
          新規ランクインのみ
        </label>
        <label class="settings-form__checkbox">
          <input type="checkbox" name="dropout_only" value="1" <?= $filterDropoutOnly ? 'checked' : '' ?>>
          ランク外落ちのみ
        </label>
      </div>
      <div class="modal__actions">
        <button type="button" class="modal__button modal__button--ghost" data-settings-close>キャンセル</button>
        <button type="submit" class="modal__button">保存</button>
      </div>
    </form>
  </div>
</div>
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