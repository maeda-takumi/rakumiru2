<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
configureSessionCookie();

session_start();

function renderLineOnlyMessage(): void {
  http_response_code(403);
  echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>パスワード発行</title></head><body>';
  echo '<p>専用LINEからログインしてください</p>';
  echo '</body></html>';
  exit;
}

if (empty($_SESSION['line_user_id'])) {
  header('Location: line_login.php');
  exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

try {
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  renderLineOnlyMessage();
}

$lineUserId = (string) $_SESSION['line_user_id'];
$stmt = $pdo->prepare('SELECT id, password FROM users WHERE line_user_id = :line_user_id LIMIT 1');
$stmt->execute(['line_user_id' => $lineUserId]);
$user = $stmt->fetch();

if (!$user) {
  renderLineOnlyMessage();
}

if (!empty($user['password'])) {
  header('Location: login.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed'])) {
  unset($_SESSION['issued_password']);
  header('Location: login.php');
  exit;
}

$issuedPassword = $_SESSION['issued_password'] ?? null;

if ($issuedPassword === null) {
  $issuedPassword = rtrim(strtr(base64_encode(random_bytes(9)), '+/', '-_'), '=');
  $hash = password_hash($issuedPassword, PASSWORD_DEFAULT);
  $update = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
  $update->execute([
    'password' => $hash,
    'id' => $user['id'],
  ]);
  $_SESSION['issued_password'] = $issuedPassword;
}

?><!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>パスワード発行</title>
</head>
<body>
  <h1>ログインパスワード発行</h1>
  <p>以下のパスワードをメモしてください。パスワードはこの画面でのみ確認できます。</p>
  <p style="font-size: 20px; font-weight: bold;">
    <?php echo htmlspecialchars($issuedPassword, ENT_QUOTES, 'UTF-8'); ?>
  </p>
  <form method="post" action="">
    <button type="submit" name="proceed" value="1">ログイン画面へ進む</button>
  </form>
</body>
</html>
