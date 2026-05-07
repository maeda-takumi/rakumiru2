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

function getGeminiModelName(): string {
  if (defined('GEMINI_MODEL') && trim((string) GEMINI_MODEL) !== '') {
    return trim((string) GEMINI_MODEL);
  }
  return 'gemini-3.1-flash-lite-preview';
}

function buildGeminiGenerateContentUrl(string $model, string $apiKey): string {
  $normalizedModel = preg_replace('#^models/#', '', trim($model));
  if (!is_string($normalizedModel) || $normalizedModel === '') {
    $normalizedModel = getGeminiModelName();
  }

  return 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($normalizedModel) . ':generateContent?key=' . urlencode($apiKey);
}
function fetchActiveAiPrompt(PDO $pdo): ?string {
  $stmt = $pdo->prepare('SELECT prompt FROM ai_modes WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
  $stmt->execute();
  $prompt = $stmt->fetchColumn();
  return normalizeAiPrompt($prompt);
}

function normalizeAiPrompt($prompt): ?string {
  if (!is_string($prompt)) {
    return null;
  }
  $trimmed = trim($prompt);
  if ($trimmed === '' || mb_strtolower($trimmed) === 'none') {
    return null;
  }
  return $trimmed;
}

function fetchUserAiPrompt(PDO $pdo, int $userId): ?string {
  $stmt = $pdo->prepare(
    'SELECT am.prompt
     FROM users u
     JOIN ai_modes am ON u.ai_mode_id = am.id
     WHERE u.id = :user_id AND am.is_active = 1
     LIMIT 1'
  );
  $stmt->execute(['user_id' => $userId]);
  $prompt = $stmt->fetchColumn();
  return normalizeAiPrompt($prompt);
}
// function fetchUserGeminiApiKey(PDO $pdo, int $userId): ?string {
//   $stmt = $pdo->prepare('SELECT gemini_api_key FROM users WHERE id = :id LIMIT 1');
//   $stmt->execute(['id' => $userId]);
//   $apiKey = $stmt->fetchColumn();
//   $apiKey = is_string($apiKey) ? trim($apiKey) : '';
//   return $apiKey !== '' ? $apiKey : null;
// }
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
