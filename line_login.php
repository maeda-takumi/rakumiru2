<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_start();

$state = bin2hex(random_bytes(16));
$nonce = bin2hex(random_bytes(16));
$_SESSION['line_state'] = $state;
$_SESSION['line_nonce'] = $nonce;

$redirectUri = sprintf('%s/line_callback.php', rtrim((string) filter_input(INPUT_SERVER, 'HTTP_ORIGIN'), '/'));
$redirectUri = 'https://totalappworks.com/rakumiru/test/line_callback.php';

if (!$redirectUri || $redirectUri === '/line_callback.php') {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $redirectUri = sprintf('%s://%s/line_callback.php', $scheme, $host);
}

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
