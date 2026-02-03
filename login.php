<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
configureSessionCookie();

session_start();

function renderLineOnlyMessage(): void {
  http_response_code(403);
  echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>ログイン</title></head><body>';
  echo '<p>専用LINEからログインしてください</p>';
  echo '</body></html>';
  exit;
}

if (empty($_SESSION['line_user_id'])) {
  header('Location: line_login.php');
  exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$error = null;
$lineUserId = (string) $_SESSION['line_user_id'];
$user = null;

try {
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  renderLineOnlyMessage();
}

$stmt = $pdo->prepare('SELECT id, password FROM users WHERE line_user_id = :line_user_id LIMIT 1');
$stmt->execute(['line_user_id' => $lineUserId]);
$user = $stmt->fetch();

if (!$user) {
  renderLineOnlyMessage();
}

if (empty($user['password'])) {
  header('Location: password_issue.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $password = (string) filter_input(INPUT_POST, 'password');
  if ($password === '') {
    $error = 'パスワードを入力してください。';
  } elseif (!password_verify($password, (string) $user['password'])) {
    $error = 'パスワードが正しくありません。';
  } else {
    session_regenerate_id(true);
    $_SESSION['password_authenticated'] = true;
    header('Location: index.php');
    exit;
  }
}

  include __DIR__ . '/header.php';
  ?>
  <h1>ログイン</h1>
  <?php if ($error): ?>
    <p style="color: red;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <form method="post" action="">
    <label for="line_user_id">LINE ID</label><br>
    <input type="text" id="line_user_id" name="line_user_id" value="<?php echo htmlspecialchars($lineUserId, ENT_QUOTES, 'UTF-8'); ?>" readonly autocomplete="username"><br>
    <label for="password">パスワード</label><br>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <button type="submit">ログイン</button>
  </form>
<?php
include __DIR__ . '/footer.php';
?>