<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/password_crypto.php';
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
function columnExists(PDO $pdo, string $table, string $column): bool {
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column');
  $stmt->execute([
    'schema' => DB_NAME,
    'table' => $table,
    'column' => $column,
  ]);
  return (int) $stmt->fetchColumn() > 0;
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
$consentError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed'])) {
  if (empty($_POST['agree_terms'])) {
    $consentError = '利用規約とプライバシーポリシーへの同意が必要です。';
  } else {
    header('Location: login.php');
    exit;
  }
}

$issuedPassword = $_SESSION['issued_password'] ?? null;
$hasPlainPassword = columnExists($pdo, 'users', 'password_plain');
$passwordFields = $hasPlainPassword ? 'password = :password, password_plain = :password_plain' : 'password = :password';
$passwordParams = [
  'password' => null,
  'password_plain' => null,
];


if ($issuedPassword !== null) {
  $encrypted = encryptPassword($issuedPassword);
  $passwordParams['password'] = $encrypted;
} else {
  $issuedPassword = rtrim(strtr(base64_encode(random_bytes(9)), '+/', '-_'), '=');
  $encrypted = encryptPassword($issuedPassword);
  $passwordParams['password'] = $encrypted;
  $_SESSION['issued_password'] = $issuedPassword;
}

$update = $pdo->prepare("UPDATE users SET {$passwordFields} WHERE id = :id");
$updateParams = [
  'password' => $passwordParams['password'],
  'id' => $user['id'],
];
if ($hasPlainPassword) {
  $updateParams['password_plain'] = null;
}
$update->execute($updateParams);
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
        <div class="terms-consent">
          <p class="terms-text">続行するには利用規約とプライバシーポリシーをご確認ください。</p>
          <div class="terms-links">
            <button class="terms-link" type="button" data-modal-target="terms">利用規約を開く</button>
            <button class="terms-link" type="button" data-modal-target="privacy">プライバシーポリシーを開く</button>
          </div>
          <label class="terms-checkbox">
            <input type="checkbox" name="agree_terms" value="1" required>
            利用規約とプライバシーポリシーに同意します
          </label>
          <?php if (!empty($consentError)): ?>
            <p class="auth-error" role="alert"><?= htmlspecialchars($consentError, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </div>
        <button class="auth-button" type="submit" name="proceed" value="1">ログイン画面へ進む</button>
      </form>
    </main>
  </div>
  <div class="terms-modal" id="terms-modal" aria-hidden="true">
    <div class="terms-modal__backdrop" data-modal-close></div>
    <div class="terms-modal__panel" role="dialog" aria-modal="true" aria-labelledby="terms-modal-title">
      <div class="terms-modal__header">
        <h2 class="terms-modal__title" id="terms-modal-title">利用規約 / プライバシーポリシー</h2>
        <button class="terms-modal__close" type="button" data-modal-close aria-label="閉じる">×</button>
      </div>
      <div class="terms-modal__content" data-modal-content="terms">
        <h3>利用規約</h3>
        <div class="terms-modal__scroll">
          <p>本利用規約（以下「本規約」）は、楽天ランキング差分表示アプリ（以下「本サービス」）の利用条件を定めるものです。ユーザーは本サービスを利用することで本規約に同意したものとみなされます。</p>
          <h4>第1条（適用）</h4>
          <p>本規約は、本サービスの利用に関する一切の関係に適用されます。</p>
          <h4>第2条（サービス内容）</h4>
          <ol>
            <li>本サービスは、楽天API等で取得したランキングデータの差分をスマートフォン向けに表示する情報提供サービスです。</li>
            <li>本サービスは情報の正確性・完全性・最新性を保証するものではありません。</li>
          </ol>
          <h4>第3条（アカウント・認証）</h4>
          <ol>
            <li>本サービスの利用にはLINEログインによる認証が必要です。</li>
            <li>必要に応じて、追加のパスワード認証を行います。</li>
          </ol>
          <h4>第4条（禁止事項）</h4>
          <ul>
            <li>法令または公序良俗に反する行為</li>
            <li>本サービスの運営を妨害する行為</li>
            <li>不正アクセス、改ざん、リバースエンジニアリング等</li>
            <li>他者の権利侵害・迷惑行為</li>
            <li>当社が不適切と判断する行為</li>
          </ul>
          <h4>第5条（免責）</h4>
          <ol>
            <li>本サービスにより提供される情報により生じた損害について、当社は一切の責任を負いません。</li>
            <li>LINEや楽天API等の外部サービスの停止・仕様変更等により、本サービスが利用できない場合があります。</li>
          </ol>
          <h4>第6条（サービスの変更・停止）</h4>
          <p>当社は、事前の通知なく本サービスの内容の変更・停止を行うことがあります。</p>
          <h4>第7条（規約の変更）</h4>
          <p>当社は必要に応じて本規約を変更できるものとし、変更後は本サービス上での告知等により通知します。</p>
          <h4>第8条（準拠法・管轄）</h4>
          <p>本規約は日本法に準拠し、本サービスに関する紛争は当社所在地を管轄する裁判所を第一審の専属的合意管轄とします。</p>
        </div>
      </div>
      <div class="terms-modal__content" data-modal-content="privacy" hidden>
        <h3>プライバシーポリシー</h3>
        <div class="terms-modal__scroll">
          <p>本サービスは、ユーザーの個人情報を以下のとおり取り扱います。</p>
          <h4>1. 取得する情報</h4>
          <ul>
            <li>LINEユーザーID</li>
            <li>LINEプロフィール情報（表示名、プロフィール画像URL）</li>
            <li>ログイン日時・アクセス履歴などの利用状況</li>
            <li>ユーザーが設定した閲覧ジャンルやフィルター条件</li>
            <li>追加認証用パスワード（暗号化保存）</li>
            <li>Cookie / セッション情報</li>
          </ul>
          <h4>2. 利用目的</h4>
          <ul>
            <li>本人認証およびユーザー識別</li>
            <li>本サービスの提供・維持・改善</li>
            <li>利用状況の分析</li>
            <li>不正利用の防止</li>
            <li>問い合わせ対応</li>
          </ul>
          <h4>3. 第三者提供</h4>
          <p>法令に基づく場合を除き、本人の同意なく第三者に個人情報を提供しません。</p>
          <h4>4. 外部サービスの利用</h4>
          <p>本サービスは以下の外部サービスを利用します。</p>
          <ul>
            <li>LINEログイン（LINE株式会社）</li>
            <li>楽天API（楽天グループ株式会社）</li>
          </ul>
          <p>外部サービスの利用に伴い、各サービスのプライバシーポリシーが適用される場合があります。</p>
          <h4>5. 委託</h4>
          <p>本サービス運営に必要な範囲で、個人情報の取扱いを外部事業者に委託することがあります。その場合、適切に監督します。</p>
          <h4>6. 安全管理</h4>
          <p>取得した情報は、不正アクセス・漏えい・改ざん等を防止するため、合理的な安全対策を講じて管理します。</p>
          <h4>7. 保存期間</h4>
          <p>個人情報は、利用目的に必要な期間保持し、不要となった場合は適切に廃棄します。</p>
          <h4>8. 開示・訂正・削除</h4>
          <p>本人からの開示・訂正・削除の申し出があった場合、合理的な範囲で対応します。</p>
          <h4>9. 変更</h4>
          <p>本ポリシーは必要に応じて変更されることがあり、変更後は本サービス上で告知します。</p>
          <h4>10. お問い合わせ</h4>
          <p>本ポリシーに関するお問い合わせは、運営者が別途定める方法で受け付けます。</p>
        </div>
      </div>
    </div>
  </div>
<script>
  const copyButton = document.getElementById('copy-button');
  const passwordField = document.getElementById('issued-password');
  const status = document.getElementById('copy-status');
  const modal = document.getElementById('terms-modal');
  const modalContents = document.querySelectorAll('[data-modal-content]');
  const modalButtons = document.querySelectorAll('[data-modal-target]');
  const modalCloseButtons = document.querySelectorAll('[data-modal-close]');

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
  const openModal = (target) => {
    modalContents.forEach((section) => {
      section.hidden = section.dataset.modalContent !== target;
    });
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open');
  };

  const closeModal = () => {
    modal.setAttribute('aria-hidden', 'true');
    modal.classList.remove('is-open');
  };

  modalButtons.forEach((button) => {
    button.addEventListener('click', () => {
      openModal(button.dataset.modalTarget);
    });
  });

  modalCloseButtons.forEach((button) => {
    button.addEventListener('click', closeModal);
  });

  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });
</script>
<?php
include __DIR__ . '/footer.php';
?>
