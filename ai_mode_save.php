<?php
declare(strict_types=1);

require_once __DIR__ . '/description_helpers.php';
configureSessionCookie();
session_start();

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['line_user_id'])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => '認証が必要です。'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($_SESSION['password_authenticated'])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'パスワードの確認が必要です。'], JSON_UNESCAPED_UNICODE);
  exit;
}

$aiModeId = filter_input(INPUT_POST, 'ai_mode_id', FILTER_VALIDATE_INT);
if (!$aiModeId) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'AIモードを選択してください。'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo = createPdo();
  $userId = fetchUserId($pdo, $_SESSION['line_user_id']);
  if (!$userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ユーザー情報が取得できません。'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare('SELECT prompt FROM ai_modes WHERE id = :id AND is_active = 1 LIMIT 1');
  $stmt->execute(['id' => $aiModeId]);
  $prompt = $stmt->fetchColumn();
  if (!normalizeAiPrompt($prompt)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '選択できないAIモードです。'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare('UPDATE users SET ai_mode_id = :ai_mode_id WHERE id = :user_id');
  $stmt->execute([
    'ai_mode_id' => $aiModeId,
    'user_id' => $userId,
  ]);

  echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => '保存に失敗しました。'], JSON_UNESCAPED_UNICODE);
}
