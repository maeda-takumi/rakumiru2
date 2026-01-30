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
if (!$itemCode) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => '商品コードが指定されていません。'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Gemini APIキーが設定されていません。'], JSON_UNESCAPED_UNICODE);
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

  $stmt = $pdo->prepare(
    'SELECT rd.captured_date, rd.rank_pos, rd.price, rd.review_count, rd.point_rate,
            rd.sale_start_at, rd.sale_end_at, rd.genre_id,
            i.item_name, i.item_url, i.image_url, i.shop_name
     FROM rank_daily rd
     JOIN items i ON rd.item_code = i.item_code
     WHERE rd.item_code = :item_code
     ORDER BY rd.captured_date DESC
     LIMIT 1'
  );
  $stmt->execute(['item_code' => $itemCode]);
  $item = $stmt->fetch();

  if (!$item) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '商品情報が取得できません。'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $infoLines = [
    '商品コード: ' . $itemCode,
    '商品名: ' . ($item['item_name'] ?? '不明'),
  ];

  if (!empty($item['shop_name'])) {
    $infoLines[] = 'ショップ名: ' . $item['shop_name'];
  }
  if (!empty($item['rank_pos'])) {
    $infoLines[] = 'ランキング: ' . $item['rank_pos'] . '位';
  }
  if (!empty($item['price'])) {
    $infoLines[] = '価格: ¥' . number_format((int) $item['price']);
  }
  if (!empty($item['review_count'])) {
    $infoLines[] = 'レビュー数: ' . number_format((int) $item['review_count']);
  }
  if (!empty($item['point_rate'])) {
    $infoLines[] = 'ポイント倍率: ' . (int) $item['point_rate'] . '%';
  }
  if (!empty($item['sale_start_at']) && !empty($item['sale_end_at'])) {
    $infoLines[] = 'セール期間: ' . $item['sale_start_at'] . ' 〜 ' . $item['sale_end_at'];
  }
  if (!empty($item['captured_date'])) {
    $infoLines[] = '取得日: ' . $item['captured_date'];
  }

  $prompt = "あなたはEC向けの商品コピーライターです。以下の商品情報を元に、楽天ROOM投稿用の商品説明文を作成してください。\n"
    . "条件:\n"
    . "- 日本語で120〜200文字程度\n"
    . "- 数値情報（価格・レビュー数・ポイント倍率など）を自然に含める\n"
    . "- 誇張表現は避け、具体的で読みやすい文章にする\n"
    . "- 出力は本文のみで、箇条書きや見出しは使わない\n\n"
    . implode("\n", $infoLines);

  $payload = [
    'contents' => [
      [
        'parts' => [
          ['text' => $prompt],
        ],
      ],
    ],
    'generationConfig' => [
      'temperature' => 0.7,
      'maxOutputTokens' => 400,
    ],
  ];

  $model = 'models/gemini-1.5-flash';
  $apiUrl = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=' . urlencode(GEMINI_API_KEY);
  $ch = curl_init($apiUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);
  $responseBody = curl_exec($ch);
  $responseCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($responseBody === false) {
    $errorMessage = curl_error($ch);
    curl_close($ch);
    throw new RuntimeException($errorMessage ?: 'Gemini APIとの通信に失敗しました。');
  }
  curl_close($ch);

  if ($responseCode < 200 || $responseCode >= 300) {
    $detail = '';
    if (is_string($responseBody) && $responseBody !== '') {
      $detail = 'レスポンス: ' . mb_substr($responseBody, 0, 300);
    }
    $status = 'HTTP ' . $responseCode;
    $message = 'Gemini APIの呼び出しに失敗しました。' . ($detail ? " ({$status}) {$detail}" : " ({$status})");
    throw new RuntimeException($message);
  }

  $responseData = json_decode($responseBody, true);
  if (!is_array($responseData)) {
    throw new RuntimeException('Gemini APIの応答を解析できませんでした。');
  }

  $description = trim((string) ($responseData['candidates'][0]['content']['parts'][0]['text'] ?? ''));
  if ($description === '') {
    throw new RuntimeException('AI説明が取得できませんでした。');
  }

  saveItemDescription($pdo, $userId, $itemCode, $description);

  echo json_encode(['success' => true, 'description' => $description], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(
    [
      'success' => false,
      'message' => 'AI説明の生成に失敗しました。',
      'detail' => $e->getMessage(),
    ],
    JSON_UNESCAPED_UNICODE
  );
}
