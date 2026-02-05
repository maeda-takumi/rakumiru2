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
  "あなたは「楽天ROOM」で商品を販売するプロです。\n"
  . "以下の商品情報をもとに、楽天ROOM投稿用の“紹介文”を1つ生成してください。\n"
  . "\n"
  . "【必須ルール】\n"
  . "- 出力するのは生成した紹介文のみ（前置き・解説・注意書きは一切不要）\n"
  . "- 日本語で120〜200文字程度\n"
  . "- 商品の特徴を簡潔にまとめ、魅力が伝わるように訴求する\n"
  . "- 閲覧者の購買意欲を掻き立てる文言を自然に入れる\n"
  . "- ハッシュタグ（#）を文末に2〜6個入れる（検索されやすい一般語中心）\n"
  . "- 数値情報（価格・レビュー数・ポイント倍率・ランキング等）があれば“生の数字は出さず”自然に言い換えて織り込む\n"
  . "- 価格/ランキング/レビュー数/ポイント倍率/日付など、入力に含まれる具体的な数値は本文にそのまま書かない（例外なし）\n"
  . "- 数値の言い換えルール：\n"
  . "  ・ランキング: 1〜3位=最上位、4〜10位=上位、11〜30位=上位入り、31位以下=注目\n"
  . "  ・レビュー数: 〜999=レビューあり、1000〜4999=レビュー多数、5000〜9999=高レビュー、10000〜29999=約1〜3万件、30000〜=約3万件以上\n"
  . "  ・価格: 具体的金額は出さず「お手頃/手に取りやすい/しっかり価格/ご褒美価格」などの表現にする\n"
  . "  ・ポイント倍率: 具体的%は出さず「ポイント還元あり/還元アップのタイミング」などの表現にする\n"
  . "  ・セール期間/取得日: 具体的な日付は出さず「セール中/期間限定/今のうち」などの表現にする\n"
  . "- 誇張表現や断定しすぎ（最強/絶対/必ず等）は避け、具体的で読みやすい文章にする\n"
  . "- 箇条書き、見出し、記号の羅列は使わない（1〜2文の自然な文章）\n"
  . "- 入力情報に“説明文”は無いので、下の項目（商品名・ショップ名・ランキング・価格・レビュー数・ポイント倍率・セール期間・取得日）から訴求点を組み立てる\n"
  . "- 同じ言い回し・構成・切り口にならないように毎回表現を変える（テンプレ感を出さない）\n"
  . "- 日本語のみで出力する（英語・韓国語・中国語など他言語は禁止）\n"
  . "- 入力情報にない事実（受賞歴/No.1/大賞/公式評価/効果の断定など）は書かない\n"
  . "\n"
  . "【商品情報】\n"
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
