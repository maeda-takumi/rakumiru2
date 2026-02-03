<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
configureSessionCookie();

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$error = null;
$pdo = null;

try {
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  $error = 'データベースに接続できませんでした。';
}

$searchQuery = trim((string) filter_input(INPUT_GET, 'q'));
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = $page && $page > 0 ? $page : 1;
$perPage = 30;
$offset = ($page - 1) * $perPage;

$users = [];
$totalCount = 0;

if ($pdo) {
  $whereSql = '';
  $params = [];
  if ($searchQuery !== '') {
    $whereSql = 'WHERE line_name LIKE :search';
    $params['search'] = '%' . $searchQuery . '%';
  }

  $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users {$whereSql}");
  $countStmt->execute($params);
  $totalCount = (int) $countStmt->fetchColumn();

  $sql = "SELECT id, line_name, img, last_login_at, active, password
          FROM users
          {$whereSql}
          ORDER BY id DESC
          LIMIT :limit OFFSET :offset";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
  }
  $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $users = $stmt->fetchAll();
}

$totalPages = $totalCount > 0 ? (int) ceil($totalCount / $perPage) : 1;
$startPage = max(1, $page - 2);
$endPage = min($totalPages, $page + 2);

$formatLastLogin = static function (?string $value): string {
  if (!$value) {
    return '未ログイン';
  }
  try {
    $date = new DateTime($value, new DateTimeZone('Asia/Tokyo'));
    return $date->format('Y-m-d H:i:s');
  } catch (Exception $e) {
    return $value;
  }
};

$formatPassword = static function (?string $value): string {
  $trimmed = trim((string) $value);
  if ($trimmed === '') {
    return '未設定';
  }
  return $trimmed;
};

$initialFromName = static function (?string $value): string {
  $trimmed = trim((string) $value);
  if ($trimmed === '') {
    return 'U';
  }
  $firstChar = mb_substr($trimmed, 0, 1);
  return mb_strtoupper($firstChar);
};

require_once __DIR__ . '/header.php';
?>
<section class="panel admin-panel">
  <div class="panel__header admin-panel__header">
    <div>
      <h2 class="panel__title">ユーザ管理</h2>
      <p class="panel__summary">LINE名で検索し、アクティブ状況を切り替えできます。</p>
    </div>
  </div>

  <form class="admin-search" method="get" action="">
    <label class="admin-search__field">
      <span class="admin-search__label">LINE名検索</span>
      <input
        type="text"
        name="q"
        value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
        placeholder="LINE名を入力"
        class="admin-search__input"
      />
    </label>
    <button class="admin-search__button" type="submit">検索</button>
  </form>

  <?php if ($error): ?>
    <p class="admin-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
  <?php else: ?>
    <div class="admin-table">
      <div class="admin-table__header">
        <span>LINE名</span>
        <span>画像</span>
        <span>最終ログイン</span>
        <span>Active</span>
        <span>Password</span>
      </div>
      <?php foreach ($users as $user): ?>
        <?php
          $lineName = $user['line_name'] ?? '';
          $imgUrl = $user['img'] ?? '';
          $active = (int) ($user['active'] ?? 0) === 1;
          $password = $user['password'] ?? '';
        ?>
        <div class="admin-table__row" data-user-row>
          <div class="admin-table__cell admin-table__cell--name">
            <span class="admin-name"><?= htmlspecialchars($lineName, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="admin-table__cell">
            <?php if ($imgUrl): ?>
              <img src="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($lineName, ENT_QUOTES, 'UTF-8') ?>" class="admin-avatar" />
            <?php else: ?>
              <div class="admin-avatar admin-avatar--placeholder"><?= htmlspecialchars($initialFromName($lineName), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>
          <div class="admin-table__cell">
            <span class="admin-login"><?= htmlspecialchars($formatLastLogin($user['last_login_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="admin-table__cell admin-table__cell--toggle">
            <label class="toggle">
              <input
                type="checkbox"
                class="toggle__input"
                data-user-toggle
                data-user-id="<?= (int) $user['id'] ?>"
                <?= $active ? 'checked' : '' ?>
                aria-checked="<?= $active ? 'true' : 'false' ?>"
              />
              <span class="toggle__slider" aria-hidden="true"></span>
            </label>
            <span class="admin-toggle-status" data-toggle-status></span>
          </div>
          <div class="admin-table__cell">
            <span class="admin-password"><?= htmlspecialchars($formatPassword($password), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="admin-pagination">
      <span class="admin-pagination__count">全<?= number_format($totalCount) ?>件</span>
      <div class="admin-pagination__controls">
        <?php if ($page > 1): ?>
          <a class="admin-pagination__link" href="?<?= http_build_query(['q' => $searchQuery, 'page' => $page - 1]) ?>">前へ</a>
        <?php endif; ?>

        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
          <a class="admin-pagination__link <?= $p === $page ? 'is-active' : '' ?>" href="?<?= http_build_query(['q' => $searchQuery, 'page' => $p]) ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <a class="admin-pagination__link" href="?<?= http_build_query(['q' => $searchQuery, 'page' => $page + 1]) ?>">次へ</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</section>

<script src="../js/admin.js?v=<?= time() ?>"></script>
<?php require_once __DIR__ . '/../footer.php'; ?>
