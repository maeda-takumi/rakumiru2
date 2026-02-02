<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

configureSessionCookie();

$state = bin2hex(random_bytes(16));
$nonce = bin2hex(random_bytes(16));

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
try {
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  exit('Database connection failed.');
}

$cleanup = $pdo->prepare('DELETE FROM oauth_state WHERE created_at < (NOW() - INTERVAL 15 MINUTE)');
$cleanup->execute();

$stmt = $pdo->prepare('INSERT INTO oauth_state (state, nonce, created_at) VALUES (:state, :nonce, NOW())');
$stmt->execute([
  'state' => $state,
  'nonce' => $nonce,
]);

$redirectUri = LINE_REDIRECT_URI;

$params = http_build_query([
  'response_type' => 'code',
  'client_id' => LINE_CHANNEL_ID,
  'redirect_uri' => $redirectUri,
  'state' => $state,
  'scope' => 'openid profile',
  'nonce' => $nonce,
], '', '&', PHP_QUERY_RFC3986);

$authUrl = 'https://access.line.me/oauth2/v2.1/authorize?' . $params;

header('Location: ' . $authUrl);
exit;