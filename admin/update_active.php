<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=UTF-8');

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$active = filter_input(INPUT_POST, 'active', FILTER_VALIDATE_INT);

if (!$userId || !in_array($active, [0, 1], true)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_parameters'], JSON_UNESCAPED_UNICODE);
  exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

try {
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $stmt = $pdo->prepare('UPDATE users SET active = :active WHERE id = :id');
  $stmt->execute([
    'active' => $active,
    'id' => $userId,
  ]);
  echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error'], JSON_UNESCAPED_UNICODE);
}
