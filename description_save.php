<?php
declare(strict_types=1);

require_once __DIR__ . '/description_helpers.php';
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
  $pdo = createPdo();
  $userId = fetchUserId($pdo, $_SESSION['line_user_id']);
  if (!$userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ユーザー情報が取得できません。'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  saveItemDescription($pdo, (int) $userId, $itemCode, $description);


  echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => '保存に失敗しました。'], JSON_UNESCAPED_UNICODE);
}
