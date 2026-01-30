<?php
declare(strict_types=1);

/**
 * 処理.php
 * - 通常アクセス: HTMLを返す（画面）
 * - ?api=1 付き: JSON API を返す
 *
 * DB接続:
 * 1) もし同階層に config.php があればそれを読む（return配列）
 *    例:
 *    return [
 *      'db' => ['host'=>'localhost','name'=>'ss911157_rakumiru2','user'=>'xxx','pass'=>'yyy','charset'=>'utf8mb4']
 *    ];
 * 2) 無ければ下の $DB_* を直で設定
 */

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tokyo');

// ------------------------------------
// DB設定（config.php があれば優先）
// ------------------------------------
$DB_HOST = 'localhost';
$DB_NAME = 'ss911157_rakumiru2';
$DB_USER = 'ss911157_sedo';
$DB_PASS = 'sedorisedori';
$DB_CHARSET = 'utf8mb4';

$configPath = __DIR__ . '/config.php';
if (is_file($configPath)) {
    $cfg = include $configPath;
    if (is_array($cfg) && isset($cfg['db']) && is_array($cfg['db'])) {
        $DB_HOST = (string)($cfg['db']['host'] ?? $DB_HOST);
        $DB_NAME = (string)($cfg['db']['name'] ?? $DB_NAME);
        $DB_USER = (string)($cfg['db']['user'] ?? $DB_USER);
        $DB_PASS = (string)($cfg['db']['pass'] ?? $DB_PASS);
        $DB_CHARSET = (string)($cfg['db']['charset'] ?? $DB_CHARSET);
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET;
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function json_ok(array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_ng(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
function strq(string $v): string { return trim($v); }
function intq($v, int $default = 0): int {
    if ($v === null || $v === '') return $default;
    if (!preg_match('/^-?\d+$/', (string)$v)) return $default;
    return (int)$v;
}

// ------------------------------------
// API
// ------------------------------------
$isApi = (($_GET['api'] ?? '') === '1');

if ($isApi) {
    try {
        $action = strq((string)($_GET['action'] ?? $_POST['action'] ?? ''));

        if ($action === '') json_ng('action is required');

        // 1) ジャンル一覧（検索・親指定・active絞り）
        if ($action === 'list_genres') {
            $q = strq((string)($_GET['q'] ?? ''));
            $parentId = ($_GET['parent_id'] ?? '');
            $parentId = ($parentId === '' ? null : (string)$parentId);
            $activeOnly = ((string)($_GET['active_only'] ?? '0') === '1');

            $where = [];
            $params = [];

            if ($q !== '') {
                $where[] = '(genre_name LIKE :q OR CAST(genre_id AS CHAR) LIKE :q)';
                $params[':q'] = '%' . $q . '%';
            }
            if ($parentId !== null) {
                if ($parentId === 'null') {
                    $where[] = 'parent_genre_id IS NULL';
                } else {
                    $where[] = 'parent_genre_id = :pid';
                    $params[':pid'] = (int)$parentId;
                }
            }
            if ($activeOnly) {
                $where[] = 'is_active = 1';
            }

            $sql = "SELECT genre_id, parent_genre_id, genre_name, depth, is_active, updated_at
                    FROM genres";
            if ($where) $sql .= " WHERE " . implode(' AND ', $where);
            $sql .= " ORDER BY depth ASC, genre_name ASC";

            $st = db()->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();

            json_ok(['genres' => $rows]);
        }

        // 2) 子ジャンル（ツリー用）
        if ($action === 'children') {
            $parentId = ($_GET['parent_id'] ?? 'null');
            if ($parentId === 'null') {
                $sql = "SELECT genre_id, parent_genre_id, genre_name, depth, is_active, updated_at
                        FROM genres
                        WHERE parent_genre_id IS NULL
                        ORDER BY genre_name ASC";
                $st = db()->prepare($sql);
                $st->execute();
            } else {
                $pid = intq($parentId, -1);
                if ($pid < 0) json_ng('invalid parent_id');
                $sql = "SELECT genre_id, parent_genre_id, genre_name, depth, is_active, updated_at
                        FROM genres
                        WHERE parent_genre_id = :pid
                        ORDER BY genre_name ASC";
                $st = db()->prepare($sql);
                $st->execute([':pid' => $pid]);
            }
            json_ok(['children' => $st->fetchAll()]);
        }

        // 3) ジャンル詳細
        if ($action === 'get_genre') {
            $gid = intq($_GET['genre_id'] ?? null, 0);
            if ($gid <= 0) json_ng('invalid genre_id');

            $st = db()->prepare("SELECT genre_id, parent_genre_id, genre_name, depth, is_active, updated_at
                                 FROM genres WHERE genre_id = :gid LIMIT 1");
            $st->execute([':gid' => $gid]);
            $genre = $st->fetch();
            if (!$genre) json_ng('genre not found', 404);

            // 子数
            $st2 = db()->prepare("SELECT COUNT(*) AS cnt FROM genres WHERE parent_genre_id = :gid");
            $st2->execute([':gid' => $gid]);
            $childCnt = (int)($st2->fetch()['cnt'] ?? 0);

            json_ok(['genre' => $genre, 'child_count' => $childCnt]);
        }

        // 4) 最新ランキング（rank_daily の最新 captured_date を使う）
        if ($action === 'latest_rankings') {
            $gid = intq($_GET['genre_id'] ?? null, 0);
            if ($gid <= 0) json_ng('invalid genre_id');

            // 最新日付
            $st = db()->prepare("SELECT MAX(captured_date) AS d FROM rank_daily WHERE genre_id = :gid");
            $st->execute([':gid' => $gid]);
            $d = $st->fetch()['d'] ?? null;
            if (!$d) json_ok(['captured_date' => null, 'rows' => []]);

            $sql = "
              SELECT
                rd.captured_date,
                rd.rank_pos,
                rd.item_code,
                rd.price,
                rd.review_count,
                rd.point_rate,
                i.item_name,
                i.item_url,
                i.image_url,
                i.shop_name,
                i.last_seen_at
              FROM rank_daily rd
              LEFT JOIN items i ON i.item_code = rd.item_code
              WHERE rd.genre_id = :gid
                AND rd.captured_date = :d
              ORDER BY rd.rank_pos ASC
              LIMIT 200
            ";
            $st2 = db()->prepare($sql);
            $st2->execute([':gid' => $gid, ':d' => $d]);
            json_ok(['captured_date' => $d, 'rows' => $st2->fetchAll()]);
        }

        // 5) 30日統計（rank_stats_30d）
        if ($action === 'stats_30d') {
            $gid = intq($_GET['genre_id'] ?? null, 0);
            if ($gid <= 0) json_ng('invalid genre_id');

            $sql = "
              SELECT
                s.genre_id,
                s.item_code,
                s.appear_days_30d,
                s.best_rank_30d,
                s.avg_rank_30d,
                s.last_seen_date,
                s.last_rank,
                s.updated_at,
                i.item_name,
                i.item_url,
                i.image_url,
                i.shop_name,
                i.price_last,
                i.review_count_last,
                i.point_rate_last
              FROM rank_stats_30d s
              LEFT JOIN items i ON i.item_code = s.item_code
              WHERE s.genre_id = :gid
              ORDER BY s.best_rank_30d ASC, s.appear_days_30d DESC
              LIMIT 300
            ";
            $st = db()->prepare($sql);
            $st->execute([':gid' => $gid]);
            json_ok(['rows' => $st->fetchAll()]);
        }

        // 6) is_active 更新
        if ($action === 'set_active') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_ng('Method Not Allowed', 405);
            $gid = intq($_POST['genre_id'] ?? null, 0);
            $active = intq($_POST['is_active'] ?? null, 1);
            $active = ($active ? 1 : 0);
            if ($gid <= 0) json_ng('invalid genre_id');

            $st = db()->prepare("UPDATE genres SET is_active = :a WHERE genre_id = :gid");
            $st->execute([':a' => $active, ':gid' => $gid]);

            json_ok(['genre_id' => $gid, 'is_active' => $active]);
        }

        // 7) job_state
        if ($action === 'job_state') {
            $st = db()->query("SELECT job_name, last_run_at, last_run_date, status, message, cursor_genre_id, updated_at
                               FROM job_state
                               ORDER BY job_name ASC");
            json_ok(['jobs' => $st->fetchAll()]);
        }

        json_ng('unknown action');
    } catch (Throwable $e) {
        json_ng('Server error: ' . $e->getMessage(), 500);
    }
}

// ------------------------------------
// HTML（画面）
// ------------------------------------
include __DIR__ . '/header.php';
?>

<section class="layout">
  <aside class="sidebar">
    <div class="sidebar__head">
      <div class="h1">ジャンル</div>

      <div class="search">
        <input id="js-search" type="text" placeholder="ジャンル名 / ID で検索…" autocomplete="off">
        <button class="btn btn--ghost" id="js-search-clear" type="button" title="クリア">×</button>
      </div>

      <label class="toggle">
        <input type="checkbox" id="js-active-only">
        <span>activeのみ</span>
      </label>
    </div>

    <div class="tree" id="js-tree">
      <div class="muted">読み込み中…</div>
    </div>
  </aside>

  <section class="content">
    <div class="content__top">
      <div class="crumbs" id="js-crumbs">—</div>
      <div class="actions">
        <button class="btn" id="js-refresh" type="button">更新</button>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <div class="card__head">
          <div class="card__title">ジャンル詳細</div>
          <div class="card__sub" id="js-genre-sub">—</div>
        </div>
        <div class="card__body" id="js-genre-detail">
          <div class="muted">左のツリーからジャンルを選択してください</div>
        </div>
      </div>

      <div class="card">
        <div class="card__head">
          <div class="card__title">ジョブ状態</div>
          <div class="card__sub">job_state</div>
        </div>
        <div class="card__body" id="js-job-state">
          <div class="muted">読み込み中…</div>
        </div>
      </div>
    </div>

    <div class="card mt16">
      <div class="tabs">
        <button class="tab is-active" data-tab="rank">最新ランキング</button>
        <button class="tab" data-tab="stats">30日統計</button>
      </div>

      <div class="card__body">
        <div class="tabpane" id="js-tab-rank">
          <div class="muted">ジャンルを選択すると表示されます</div>
        </div>

        <div class="tabpane is-hidden" id="js-tab-stats">
          <div class="muted">ジャンルを選択すると表示されます</div>
        </div>
      </div>
    </div>

  </section>
</section>

<?php
include __DIR__ . '/footer.php';
