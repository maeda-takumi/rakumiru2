<?php
// Rakuten Ichiba Item Search API 例
$appId = '1025854062340321330';
$keyword = 'テスト';
$url = 'https://app.rakuten.co.jp/services/api/IchibaItem/Search/20170706'
     . '?applicationId=' . urlencode($appId)
     . '&keyword=' . urlencode($keyword)
     . '&format=json';

$response = file_get_contents($url);
$data = json_decode($response, true);

if (!empty($data['Items'][0]['Item'])) {
  $item = $data['Items'][0]['Item'];
  echo "itemCode: " . ($item['itemCode'] ?? 'N/A') . PHP_EOL;
  echo "itemName: " . ($item['itemName'] ?? 'N/A') . PHP_EOL;
  echo "itemUrl: " . ($item['itemUrl'] ?? 'N/A') . PHP_EOL;

  // 数値IDっぽいフィールドがあるか確認用に全部出す
  foreach ($item as $key => $value) {
    if (is_scalar($value)) {
      echo $key . ': ' . $value . PHP_EOL;
    }
  }
}
