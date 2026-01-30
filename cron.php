<?php
declare(strict_types=1);

/**
 * Rakuten Daily Ranking Collector (cron)
 * - Fetch ranking per genre (daily)
 * - Upsert items
 * - Upsert rank_daily (same day overwrite)
 * - Save job_state for resume
 * - Optional: cleanup old rank_daily
 */

// ==============================
// config
// ==============================
date_default_timezone_set('Asia/Tokyo');

$config = [
    'db' => [
    'dsn'  => 'mysql:host=localhost;dbname=ss911157_rakumiru2;charset=utf8mb4',
    'user' => 'ss911157_sedo',
    'pass' => 'sedorisedori',
    ],

  'rakuten' => [
    'applicationId' => '1025854062340321330',
    'endpoint'      => 'https://app.rakuten.co.jp/services/api/IchibaItem/Ranking/20170628',
    'hits'          => 30,
    'period'        => null, // daily => null（realtimeなら 'realtime'）
  ],
  'job' => [
    'name'            => 'rakuten_rank_daily',
    'max_runtime_sec' => 10 * 60,  // 9分で安全終了（次回再開）
    'sleep_usec'      => 1050000, // 1.05秒（1秒1回制限の安全側）
    'cleanup_keep_days' => 14,    // rank_dailyを14日保持
  ],
  'http' => [
    'connect_timeout' => 10,
    'timeout'         => 25,
    'retry'           => 3,
    'retry_wait_ms'   => 700,
  ],
];
class HttpStatusException extends RuntimeException {
  public int $status;
  public ?string $raw;
  public int $errno;
  public string $curlErr;

  public function __construct(int $status, ?string $raw, int $errno, string $curlErr) {
    parent::__construct("HTTP failed: status={$status}, errno={$errno}, err={$curlErr}");
    $this->status  = $status;
    $this->raw     = $raw;
    $this->errno   = $errno;
    $this->curlErr = $curlErr;
  }
}
// ==============================
// util
// ==============================
function logln(string $msg): void {
  echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

function acquireLock(string $path): mixed {
  $fp = fopen($path, 'c');
  if (!$fp) return false;
  if (!flock($fp, LOCK_EX | LOCK_NB)) return false;
  return $fp; // keep handle until end
}

function httpGetJson(string $url, array $httpConf): array {
  $attempt = 0;
  while (true) {
    $attempt++;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => $httpConf['connect_timeout'],
      CURLOPT_TIMEOUT        => $httpConf['timeout'],
      CURLOPT_FAILONERROR    => false,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    // success
    if ($errno === 0 && $status >= 200 && $status < 300 && is_string($raw)) {
      $json = json_decode($raw, true);
      if (is_array($json)) return $json;
      throw new RuntimeException("JSON decode failed");
    }

    // 400/404 は「そのジャンルが無効」の扱いにしたいので即返す（例外で上に渡す）
    if (in_array($status, [400, 404], true)) {
      throw new HttpStatusException($status, is_string($raw) ? $raw : null, $errno, $err);
    }

    // Retry on transient errors
    $retryable = ($errno !== 0) || in_array($status, [429, 500, 502, 503, 504], true);
    if (!$retryable || $attempt >= $httpConf['retry']) {
      throw new HttpStatusException($status, is_string($raw) ? $raw : null, $errno, $err);
    }

    usleep((int)$httpConf['retry_wait_ms'] * 1000);
  }
}

// ==============================
// main
// ==============================
$start = time();
$lock = acquireLock(__DIR__ . '/collector_daily.lock');
if ($lock === false) {
  logln('Already running. exit.');
  exit(0);
}

try {
  // DB connect
  $pdo = new PDO(
    $config['db']['dsn'],
    $config['db']['user'],
    $config['db']['pass'],
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );

  $jobName = $config['job']['name'];
  $today = date('Y-m-d'); // captured_date
  $nowDt = date('Y-m-d H:i:s');

  // job_state upsert (running)
  $pdo->prepare("
    INSERT INTO job_state (job_name, last_run_at, last_run_date, status, message, cursor_genre_id)
    VALUES (:job, :at, :d, 'running', :msg, NULL)
    ON DUPLICATE KEY UPDATE
      last_run_at=VALUES(last_run_at),
      last_run_date=VALUES(last_run_date),
      status='running',
      message=VALUES(message)
  ")->execute([
    ':job' => $jobName,
    ':at'  => $nowDt,
    ':d'   => $today,
    ':msg' => 'started',
  ]);

  // resume cursor
  $st = $pdo->prepare("SELECT cursor_genre_id FROM job_state WHERE job_name=:job LIMIT 1");
  $st->execute([':job' => $jobName]);
  $cursor = $st->fetchColumn();
  $cursor = ($cursor !== false && $cursor !== null) ? (int)$cursor : null;

  // fetch genres (active)
  // cursor がある場合は「そのgenre_idより大きいもの」から再開（順序はgenre_id昇順）
  if ($cursor !== null) {
    $gStmt = $pdo->prepare("
      SELECT genre_id FROM genres
      WHERE is_active=1 AND genre_id > :cursor
      ORDER BY genre_id ASC
    ");
    $gStmt->execute([':cursor' => $cursor]);
    logln("Resume from cursor genre_id > {$cursor}");
  } else {
    $gStmt = $pdo->query("
      SELECT genre_id FROM genres
      WHERE is_active=1
      ORDER BY genre_id ASC
    ");
    logln("Start from beginning");
  }
  $genres = $gStmt->fetchAll(PDO::FETCH_COLUMN);

  // prepared statements
  $upsertItem = $pdo->prepare("
    INSERT INTO items
      (item_code, item_name, item_url, image_url, shop_code, shop_name,
       price_last, review_count_last, point_rate_last, first_seen_at, last_seen_at)
    VALUES
      (:item_code, :item_name, :item_url, :image_url, :shop_code, :shop_name,
       :price_last, :review_count_last, :point_rate_last, :first_seen_at, :last_seen_at)
    ON DUPLICATE KEY UPDATE
      item_name=VALUES(item_name),
      item_url=VALUES(item_url),
      image_url=VALUES(image_url),
      shop_code=VALUES(shop_code),
      shop_name=VALUES(shop_name),
      price_last=VALUES(price_last),
      review_count_last=VALUES(review_count_last),
      point_rate_last=VALUES(point_rate_last),
      last_seen_at=VALUES(last_seen_at)
  ");

  $upsertRank = $pdo->prepare("
    INSERT INTO rank_daily
      (captured_date, captured_at, genre_id, rank_pos, item_code, price, review_count, point_rate)
    VALUES
      (:captured_date, :captured_at, :genre_id, :rank_pos, :item_code, :price, :review_count, :point_rate)
    ON DUPLICATE KEY UPDATE
      captured_at=VALUES(captured_at),
      item_code=VALUES(item_code),
      price=VALUES(price),
      review_count=VALUES(review_count),
      point_rate=VALUES(point_rate)
  ");

  $updateCursor = $pdo->prepare("
    UPDATE job_state SET cursor_genre_id=:cursor, message=:msg WHERE job_name=:job
  ");

    $disableGenre = $pdo->prepare("
    UPDATE genres
    SET is_active=0, updated_at=NOW()
    WHERE genre_id=:gid
    ");

  $rakuten = $config['rakuten'];
  $base = $rakuten['endpoint'];
  $appId = $rakuten['applicationId'];
  $hits = (int)$rakuten['hits'];

  $processed = 0;

  foreach ($genres as $genreId) {
    $genreId = (int)$genreId;

    // time cutoff
    if ((time() - $start) > $config['job']['max_runtime_sec']) {
      logln("Time limit reached. stop safely. processed={$processed}");
      $updateCursor->execute([
        ':cursor' => $genreId,
        ':msg'    => "time limit reached at genre_id={$genreId}",
        ':job'    => $jobName,
      ]);
      // mark partial OK (running->ok ではなく error にしない)
      $pdo->prepare("UPDATE job_state SET status='ok' WHERE job_name=:job")->execute([':job'=>$jobName]);
      exit(0);
    }

    // build URL
    $qs = [
      'applicationId' => $appId,
      'genreId'       => (string)$genreId,
      'hits'          => (string)$hits,
      'page'          => '1',
      'format'        => 'json',
    ];
    if (!empty($rakuten['period'])) {
      $qs['period'] = $rakuten['period']; // realtime etc
    }
    $url = $base . '?' . http_build_query($qs);

    logln("Fetch genre={$genreId}");

    try {
    $json = httpGetJson($url, $config['http']);
    } catch (HttpStatusException $he) {

    // 400/404 はそのジャンルを無効化してスキップ
    if (in_array($he->status, [400, 404], true)) {
        logln("Skip genre={$genreId} (HTTP {$he->status}) -> set is_active=0");

        // genres を無効化
        $disableGenre->execute([':gid' => $genreId]);

        // 次回再開用にカーソルも進める（同じgenreで詰まらないように）
        $updateCursor->execute([
        ':cursor' => $genreId,
        ':msg'    => "skipped genre_id={$genreId} (HTTP {$he->status})",
        ':job'    => $jobName,
        ]);

        // レート制限
        usleep($config['job']['sleep_usec']);
        continue;
    }

    // 400/404以外は従来通り致命扱い
    throw $he;
    }


    // API response structure: items[] with item[] inside (common pattern)
    if (!isset($json['Items']) || !is_array($json['Items'])) {
      throw new RuntimeException("Invalid response: no Items (genre={$genreId})");
    }

    $pdo->beginTransaction();

    $rankPos = 0;
    foreach ($json['Items'] as $row) {
      // Normalize item payload
      // Some APIs return { "Item": { ... } } per element
      $item = $row['Item'] ?? $row;
      if (!is_array($item)) continue;

      $rankPos++;
      if ($rankPos > $hits) break;

      $itemCode = (string)($item['itemCode'] ?? '');
      if ($itemCode === '') continue;

      $itemName = (string)($item['itemName'] ?? '');
      $itemUrl  = (string)($item['itemUrl'] ?? '');

      // image: pick first if exists
      $imageUrl = '';
      if (!empty($item['mediumImageUrls'][0]['imageUrl'])) {
        $imageUrl = (string)$item['mediumImageUrls'][0]['imageUrl'];
      } elseif (!empty($item['smallImageUrls'][0]['imageUrl'])) {
        $imageUrl = (string)$item['smallImageUrls'][0]['imageUrl'];
      }

      $shopCode = (string)($item['shopCode'] ?? '');
      $shopName = (string)($item['shopName'] ?? '');

      $price = isset($item['itemPrice']) ? (int)$item['itemPrice'] : null;
      $reviewCount = isset($item['reviewCount']) ? (int)$item['reviewCount'] : null;
      $pointRate = isset($item['pointRate']) ? (int)$item['pointRate'] : null;

      // upsert items
      $upsertItem->execute([
        ':item_code' => $itemCode,
        ':item_name' => mb_substr($itemName, 0, 512),
        ':item_url'  => mb_substr($itemUrl, 0, 1024),
        ':image_url' => mb_substr($imageUrl, 0, 1024),
        ':shop_code' => mb_substr($shopCode, 0, 128),
        ':shop_name' => mb_substr($shopName, 0, 255),
        ':price_last' => $price,
        ':review_count_last' => $reviewCount,
        ':point_rate_last' => $pointRate,
        ':first_seen_at' => $nowDt,     // 初回だけ意味がある（重複時は無視される）
        ':last_seen_at'  => $nowDt,
      ]);

      // upsert rank_daily (same day overwrite)
      $upsertRank->execute([
        ':captured_date' => $today,
        ':captured_at'   => $nowDt,
        ':genre_id'      => $genreId,
        ':rank_pos'      => $rankPos,
        ':item_code'     => $itemCode,
        ':price'         => $price,
        ':review_count'  => $reviewCount,
        ':point_rate'    => $pointRate,
      ]);
    }

    $pdo->commit();

    $processed++;
    // update cursor after success
    $updateCursor->execute([
      ':cursor' => $genreId,
      ':msg'    => "processed genre_id={$genreId}",
      ':job'    => $jobName,
    ]);

    // rate limit
    usleep($config['job']['sleep_usec']);
  }

  // cleanup old snapshots
  $keep = (int)$config['job']['cleanup_keep_days'];
  if ($keep > 0) {
    $pdo->prepare("
      DELETE FROM rank_daily
      WHERE captured_date < (CURRENT_DATE - INTERVAL {$keep} DAY)
    ")->execute();
  }

  // finish
  $pdo->prepare("
    UPDATE job_state
    SET status='ok', message=:msg, cursor_genre_id=NULL
    WHERE job_name=:job
  ")->execute([
    ':msg' => "done. genres_processed={$processed}",
    ':job' => $jobName,
  ]);

  logln("Done. genres_processed={$processed}");

} catch (Throwable $e) {
  logln("ERROR: " . $e->getMessage());
  // best-effort update job_state
  try {
    if (isset($pdo) && $pdo instanceof PDO) {
      $pdo->prepare("
        INSERT INTO job_state (job_name, last_run_at, last_run_date, status, message)
        VALUES (:job, :at, :d, 'error', :msg)
        ON DUPLICATE KEY UPDATE
          status='error',
          message=VALUES(message),
          last_run_at=VALUES(last_run_at),
          last_run_date=VALUES(last_run_date)
      ")->execute([
        ':job' => $config['job']['name'],
        ':at'  => date('Y-m-d H:i:s'),
        ':d'   => date('Y-m-d'),
        ':msg' => mb_substr($e->getMessage(), 0, 1000),
      ]);
    }
  } catch (Throwable $ignore) {}
  exit(1);
}
