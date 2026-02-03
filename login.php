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


  $userId = $_SESSION['user_id'] ?? filter_input(INPUT_GET, 'id', FILTER_UNSAFE_RAW);
  $userId = is_string($userId) ? $userId : '';
  include __DIR__ . '/header.php';
  ?>
  <div class="auth-page">
    <main class="auth-card">
      <div>
        <h1 class="auth-title">ログイン</h1>
        <p class="auth-description">パスワードを入力してログインしてください。</p>
      </div>
      <form class="auth-actions" method="post" action="">
        <label class="sr-only" for="user-id">ID</label>
        <input type="text" id="user-id" name="user_id" value="<?= htmlspecialchars($userId, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" class="sr-only">
        <div class="auth-field">
          <label for="password">パスワード</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button class="auth-button" type="submit">ログインする</button>
      </form>
      <p class="auth-hint">この端末にパスワードを保存できます。</p>
    </main>
  </div>
<?php
include __DIR__ . '/footer.php';
?>