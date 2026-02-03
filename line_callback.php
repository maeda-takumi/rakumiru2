<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/password_crypto.php';
configureSessionCookie();

session_start();

function renderLineOnlyMessage(): void {
  http_response_code(403);
  $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
  $basePath = $basePath === '' ? '/' : $basePath . '/';
  $styleUrl = $basePath . 'css/style.css?v=' . time();
  $logoUrl = $basePath . 'img/logo.png';
  include __DIR__ . '/header.php';
  echo '<div class="login-issue__container">';
  echo '<div class="login-issue__card">';
  
  echo '<img src="img/logo.png" alt="RAKUMiRU" class="login-issue__logo">';
  echo '<p class="login-issue__text">ログインがブロックされました</p>';
  include __DIR__ . '/footer.php';
  exit;
}

$code = filter_input(INPUT_GET, 'code');
$state = filter_input(INPUT_GET, 'state');
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
try {
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  renderLineOnlyMessage();
}

try {
  $cleanup = $pdo->prepare('DELETE FROM oauth_state WHERE created_at < (NOW() - INTERVAL 15 MINUTE)');
  $cleanup->execute();

  $lookup = $pdo->prepare('SELECT nonce FROM oauth_state WHERE state = :state LIMIT 1');
  $lookup->execute(['state' => $state]);
  $row = $lookup->fetch();
  $dbNonce = $row['nonce'] ?? null;

  $delete = $pdo->prepare('DELETE FROM oauth_state WHERE state = :state');
  $delete->execute(['state' => $state]);
} catch (PDOException $e) {
  renderLineOnlyMessage();
}

if (!$code || !$state || !$dbNonce) {
  renderLineOnlyMessage();
}

function linePost(string $url, array $fields): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
  ]);
  $response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($response === false || $status < 200 || $status >= 300) {
    return [];
  }

  $decoded = json_decode($response, true);
  return is_array($decoded) ? $decoded : [];
}

function lineGet(string $url, string $accessToken): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
  ]);
  $response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($response === false || $status < 200 || $status >= 300) {
    return [];
  }

  $decoded = json_decode($response, true);
  return is_array($decoded) ? $decoded : [];
}
$redirectUri = LINE_REDIRECT_URI;

$tokenResponse = linePost('https://api.line.me/oauth2/v2.1/token', [
  'grant_type' => 'authorization_code',
  'code' => $code,
  'redirect_uri' => $redirectUri,
  'client_id' => LINE_CHANNEL_ID,
  'client_secret' => LINE_CHANNEL_SECRET,
]);

if (empty($tokenResponse['id_token'])) {
  renderLineOnlyMessage();
}

$lineProfile = [];
if (!empty($tokenResponse['access_token'])) {
  $lineProfile = lineGet('https://api.line.me/oauth2/v2.1/userinfo', $tokenResponse['access_token']);
}
$verifyResponse = linePost('https://api.line.me/oauth2/v2.1/verify', [
  'id_token' => $tokenResponse['id_token'],
  'client_id' => LINE_CHANNEL_ID,
]);

$lineUserId = $verifyResponse['sub'] ?? null;
$verifiedNonce = $verifyResponse['nonce'] ?? null;

if (!$lineUserId || ($verifiedNonce && !hash_equals($dbNonce, $verifiedNonce))) {
  renderLineOnlyMessage();
}

function columnExists(PDO $pdo, string $table, string $column): bool {
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column');
  $stmt->execute([
    'schema' => DB_NAME,
    'table' => $table,
    'column' => $column,
  ]);
  return (int) $stmt->fetchColumn() > 0;
}

$hasCreatedAt = columnExists($pdo, 'users', 'created_at');
$hasUpdatedAt = columnExists($pdo, 'users', 'updated_at');
$hasLastLoginAt = columnExists($pdo, 'users', 'last_login_at');
$hasLineName = columnExists($pdo, 'users', 'line_name');
$hasImg = columnExists($pdo, 'users', 'img');
$hasPassword = columnExists($pdo, 'users', 'password');
$hasActive = columnExists($pdo, 'users', 'active');

$selectFields = ['id'];
if ($hasPassword) {
  $selectFields[] = 'password';
}
if ($hasActive) {
  $selectFields[] = 'active';
}
$stmt = $pdo->prepare(sprintf('SELECT %s FROM users WHERE line_user_id = :line_user_id LIMIT 1', implode(', ', $selectFields)));
$stmt->execute(['line_user_id' => $lineUserId]);
$existingRow = $stmt->fetch();
$existing = $existingRow['id'] ?? null;
$existingPassword = $existingRow['password'] ?? null;

if ($existing && $hasActive && (int) $existingRow['active'] !== 1) {
  renderLineOnlyMessage();
}

$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
$lineName = isset($lineProfile['name']) ? trim((string) $lineProfile['name']) : '';
$lineImageUrl = isset($lineProfile['picture']) ? trim((string) $lineProfile['picture']) : '';

if ($existing) {
  $fields = [];
  if ($hasUpdatedAt) {
    $fields[] = 'updated_at = :updated_at';
  }
  if ($hasLastLoginAt) {
    $fields[] = 'last_login_at = :last_login_at';
  }
  if ($hasLineName && $lineName !== '') {
    $fields[] = 'line_name = COALESCE(NULLIF(line_name, \'\'), :line_name)';
  }

  if ($hasImg && $lineImageUrl !== '') {
    $fields[] = 'img = COALESCE(NULLIF(img, \'\'), :img)';
  }
  if ($fields) {
    $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $params = ['id' => $existing];
    if ($hasUpdatedAt) {
      $params['updated_at'] = $now;
    }
    if ($hasLastLoginAt) {
      $params['last_login_at'] = $now;
    }
    if ($hasLineName && $lineName !== '') {
      $params['line_name'] = $lineName;
    }
    if ($hasImg && $lineImageUrl !== '') {
      $params['img'] = $lineImageUrl;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
  }
} else {
  $columns = ['line_user_id'];
  $placeholders = [':line_user_id'];
  $params = ['line_user_id' => $lineUserId];
  if ($hasLineName && $lineName !== '') {
    $columns[] = 'line_name';
    $placeholders[] = ':line_name';
    $params['line_name'] = $lineName;
  }
  if ($hasImg && $lineImageUrl !== '') {
    $columns[] = 'img';
    $placeholders[] = ':img';
    $params['img'] = $lineImageUrl;
  }

  if ($hasCreatedAt) {
    $columns[] = 'created_at';
    $placeholders[] = ':created_at';
    $params['created_at'] = $now;
  }
  if ($hasUpdatedAt) {
    $columns[] = 'updated_at';
    $placeholders[] = ':updated_at';
    $params['updated_at'] = $now;
  }
  if ($hasLastLoginAt) {
    $columns[] = 'last_login_at';
    $placeholders[] = ':last_login_at';
    $params['last_login_at'] = $now;
  }

  $sql = sprintf('INSERT INTO users (%s) VALUES (%s)', implode(', ', $columns), implode(', ', $placeholders));
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  if ($hasActive) {
    $stmt = $pdo->prepare('SELECT active FROM users WHERE line_user_id = :line_user_id LIMIT 1');
    $stmt->execute(['line_user_id' => $lineUserId]);
    $activeValue = $stmt->fetchColumn();
    if ($activeValue === false || (int) $activeValue !== 1) {
      renderLineOnlyMessage();
    }
  }
}

session_regenerate_id(true);
$_SESSION['line_user_id'] = $lineUserId;
unset($_SESSION['password_authenticated']);

$needsPassword = $hasPassword && (empty($existingPassword));

if (!$existing || $needsPassword) {
  header('Location: password_issue.php');
  exit;
}

header('Location: login.php');
exit;
