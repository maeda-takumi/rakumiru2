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

  $additionalStyles = ['css/auth.css?v=' . time()];
  include __DIR__ . '/header.php';
?>

  <div class="auth-page">
    <main class="auth-card">
      <div>
        <h1 class="auth-title">パスワードを発行しました</h1>
        <p class="auth-description">下記のパスワードをコピーして保管してください。</p>
      </div>
      <div class="password-display">
        <input type="text" id="issued-password" value="<?= htmlspecialchars($issuedPassword, ENT_QUOTES, 'UTF-8') ?>" readonly>
        <button class="copy-button" type="button" id="copy-button" aria-label="パスワードをコピー">
          <img src="img/copy.png" alt="コピー">
        </button>
      </div>
      <p class="copy-status" id="copy-status" aria-live="polite"></p>
      <form class="auth-actions" method="post">
        <button class="auth-button" type="submit" name="proceed" value="1">ログイン画面へ進む</button>
      </form>
    </main>
  </div>
<script>
  const copyButton = document.getElementById('copy-button');
  const passwordField = document.getElementById('issued-password');
  const status = document.getElementById('copy-status');

  copyButton.addEventListener('click', async () => {
    const password = passwordField.value;
    if (!password) {
      status.textContent = 'コピーするパスワードがありません。';
      return;
    }

    try {
      await navigator.clipboard.writeText(password);
      status.textContent = 'クリップボードにコピーしました。';
    } catch (error) {
      passwordField.select();
      passwordField.setSelectionRange(0, password.length);
      const copied = document.execCommand('copy');
      status.textContent = copied ? 'クリップボードにコピーしました。' : 'コピーに失敗しました。';
      passwordField.setSelectionRange(0, 0);
    }
  });
</script>
<?php
include __DIR__ . '/footer.php';
?>
