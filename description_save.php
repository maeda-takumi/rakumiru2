<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['line_user_id'])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => '認証が必要です。'], JSON_UNESCAPED_UNICODE);
  exit;
}

$itemCode = filter_input(INPUT_POST, 'item_code');
$description = filter_input(INPUT_POST, 'description');

if (!$itemCode) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => '商品コードが指定されていません。'], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($description === null) {
  $description = '';
}

try {
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  $stmt = $pdo->prepare('SELECT id FROM users WHERE line_user_id = :line_user_id LIMIT 1');
  $stmt->execute(['line_user_id' => $_SESSION['line_user_id']]);
  $userId = $stmt->fetchColumn();

  if (!$userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ユーザー情報が取得できません。'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare(
    'INSERT INTO item_descriptions (user_id, item_code, description, created_at, updated_at)
     VALUES (:user_id, :item_code, :description, NOW(), NOW())
     ON DUPLICATE KEY UPDATE description = VALUES(description), updated_at = NOW()'
  );
  $stmt->execute([
    'user_id' => $userId,
    'item_code' => $itemCode,
    'description' => $description,
  ]);

  echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => '保存に失敗しました。'], JSON_UNESCAPED_UNICODE);
}
