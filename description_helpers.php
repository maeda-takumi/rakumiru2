<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function createPdo(): PDO {
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
  return new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}

function fetchUserId(PDO $pdo, string $lineUserId): ?int {
  $stmt = $pdo->prepare('SELECT id FROM users WHERE line_user_id = :line_user_id LIMIT 1');
  $stmt->execute(['line_user_id' => $lineUserId]);
  $userId = $stmt->fetchColumn();
  return $userId ? (int) $userId : null;
}

function saveItemDescription(PDO $pdo, int $userId, string $itemCode, string $description): void {
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
}
