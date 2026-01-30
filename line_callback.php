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

$code = filter_input(INPUT_GET, 'code');
$state = filter_input(INPUT_GET, 'state');
$sessionState = $_SESSION['line_state'] ?? null;
$sessionNonce = $_SESSION['line_nonce'] ?? null;

if (!$code || !$state || !$sessionState || !hash_equals($sessionState, $state)) {
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

$verifyResponse = linePost('https://api.line.me/oauth2/v2.1/verify', [
  'id_token' => $tokenResponse['id_token'],
  'client_id' => LINE_CHANNEL_ID,
]);

$lineUserId = $verifyResponse['sub'] ?? null;
$verifiedNonce = $verifyResponse['nonce'] ?? null;

if (!$lineUserId || !$sessionNonce || ($verifiedNonce && !hash_equals($sessionNonce, $verifiedNonce))) {
  renderLineOnlyMessage();
}

unset($_SESSION['line_state'], $_SESSION['line_nonce']);

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
try {
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
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

$stmt = $pdo->prepare('SELECT id FROM users WHERE line_user_id = :line_user_id LIMIT 1');
$stmt->execute(['line_user_id' => $lineUserId]);
$existing = $stmt->fetchColumn();

$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

if ($existing) {
  $fields = [];
  if ($hasUpdatedAt) {
    $fields[] = 'updated_at = :updated_at';
  }
  if ($hasLastLoginAt) {
    $fields[] = 'last_login_at = :last_login_at';
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
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
  }
} else {
  $columns = ['line_user_id'];
  $placeholders = [':line_user_id'];
  $params = ['line_user_id' => $lineUserId];

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
}

session_regenerate_id(true);
$_SESSION['line_user_id'] = $lineUserId;

header('Location: index.php');
exit;
