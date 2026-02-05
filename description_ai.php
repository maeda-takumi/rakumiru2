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
$itemCode = filter_input(INPUT_POST, 'item_code');
if (!$itemCode) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => '商品コードが指定されていません。'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo = createPdo();
  $userId = fetchUserId($pdo, $_SESSION['line_user_id']);
  $cooldownSeconds = defined('AI_DESCRIPTION_COOLDOWN_SECONDS')
    ? max(0, (int) AI_DESCRIPTION_COOLDOWN_SECONDS)
    : 30;

  if (!$userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ユーザー情報が取得できません。'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $cooldownMap = $_SESSION['description_ai_last_request_at'] ?? [];
  if (!is_array($cooldownMap)) {
    $cooldownMap = [];
  }
  $lastRequestAt = isset($cooldownMap[$userId]) ? (int) $cooldownMap[$userId] : 0;
  $elapsed = time() - $lastRequestAt;
  if ($cooldownSeconds > 0 && $lastRequestAt > 0 && $elapsed < $cooldownSeconds) {
    $remainingSeconds = $cooldownSeconds - $elapsed;
    http_response_code(429);
    echo json_encode(
      [
        'success' => false,
        'message' => "クールダウン中です。あと{$remainingSeconds}秒で再生成できます。",
        'remaining_seconds' => $remainingSeconds,
      ],
      JSON_UNESCAPED_UNICODE
    );
    exit;
  }

  $apiKey = defined('GEMINI_API_KEY') ? trim((string) GEMINI_API_KEY) : '';
  if ($apiKey === '') {
    http_response_code(400);
    echo json_encode(
      ['success' => false, 'message' => 'APIキーが未設定です。API_KEYを確認してください。'],
      JSON_UNESCAPED_UNICODE
    );
    exit;
  }

  $stmt = $pdo->prepare(
    'SELECT rd.captured_date, rd.rank_pos, rd.price, rd.review_count, rd.point_rate,
            rd.sale_start_at, rd.sale_end_at, rd.genre_id,
            i.item_name, i.item_url, i.image_url, i.shop_name
     FROM rank_daily rd
     JOIN items i ON rd.item_code = i.item_code
     WHERE rd.item_code = :item_code
     ORDER BY rd.captured_date DESC, rd.captured_at DESC
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
  // if (!empty($item['point_rate'])) {
  //   $infoLines[] = 'ポイント倍率: ' . (int) $item['point_rate'] . '%';
  // }
  if (!empty($item['sale_start_at']) && !empty($item['sale_end_at'])) {
    $infoLines[] = 'セール期間: ' . $item['sale_start_at'] . ' 〜 ' . $item['sale_end_at'];
  }
  if (!empty($item['captured_date'])) {
    $infoLines[] = '取得日: ' . $item['captured_date'];
  }
  $prompt =
  "タスク: 次の【商品情報】だけを根拠に、楽天ROOM投稿用の文章を日本語で1つ作成。\n"
  ."\n"
  ."【最優先ルール】\n"
  ."- 根拠にしてよいのは【商品情報】のみ（推測・追加情報・受賞/No.1/効果断定/素材断定/性能断定は禁止）\n"
  ."- 出力は本文とハッシュタグのみ（前置き/解説/注意書き/見出しは禁止）\n"
  ."- 本文は1行（2文まで）、2行目にハッシュタグ2〜6個（一般語中心）。空行は禁止\n"
  ."- 文字数は本文+ハッシュタグ合計で350文字以内\n"
  ."- 誇張表現（最強/絶対/必ず 等）禁止\n"
  ."- スペック説明（数値羅列・機能羅列・詳細仕様の説明）なし\n"
  ."- 体験“風”はOK（例: 〜しなくてよくなった、手間が減った、気が楽になった等）。ただし日記口調（今日/最近/朝〜等）はNG\n"
  ."\n"
  ."【文章の型（新ノウハウベース）】\n"
  ."- 1文目: 『こんな人におすすめ』が伝わるように、悩み/面倒/楽にしたい を含む（箇条書きにせず文章内で自然に）\n"
  ."- 2文目: 使い始めてからの変化を体験“風”で（例: 『〜しなくてよくなった』）\n"
  ."- 商品名に含まれるキーワードを2つ以上拾い、言い換えて本文に混ぜる\n"
  ."\n"
  ."【出力形式（厳守）】\n"
  ."1行目: 本文（2文まで）\n"
  ."2行目: ハッシュタグ2〜6個\n"
  ."※空行は禁止\n"
  ."\n"
  ."【商品情報】\n"
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

  // $model = 'models/gemini-2.5-flash-lite';
  // $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/' . $model . ':generateContent?key=' . urlencode($apiKey);

  $model = 'models/gemma-3-4b-it';
  $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/' . $model . ':generateContent?key=' . urlencode($apiKey);

  $cooldownMap[$userId] = time();
  $_SESSION['description_ai_last_request_at'] = $cooldownMap;

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
