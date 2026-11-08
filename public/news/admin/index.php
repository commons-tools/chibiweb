<?php
/**
 * chibiweb お知らせの管理画面（1ファイル）
 * はじめて開いたときにパスワードを決める。
 */
declare(strict_types=1);
require dirname(__DIR__) . '/lib.php';

session_name('chibiweb');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

$cfg      = mini_config();
$authFile = MINI_DATA . '/.auth.php';
$hasAuth  = is_file($authFile);
$logged   = !empty($_SESSION['chibiweb_ok']);
$msg      = '';

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('送信の確認に失敗しました。ページを読み込み直してください。');
    }
}
function go(string $q = ''): never {
    header('Location: ./' . ($q ? '?' . $q : ''));
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ---------- パスワード設定・ログイン ---------- */
$setupKey = trim((string) ($cfg['setup_key'] ?? ''));
if ($action === 'setup' && !$hasAuth) {
    check_csrf();
    $pw = (string) ($_POST['password'] ?? '');
    if ($setupKey === '') {
        $msg = 'config.php の setup_key（合言葉）が設定されていません。';
    } elseif (!hash_equals($setupKey, (string) ($_POST['setup_key'] ?? ''))) {
        sleep(2); // 総当たり対策（簡易）
        $msg = '合言葉が違います。';
    } elseif (mb_strlen($pw) < 10) {
        $msg = 'パスワードは10文字以上にしてください。';
    } else {
        file_put_contents($authFile, '<?php return ' . var_export(['hash' => password_hash($pw, PASSWORD_DEFAULT)], true) . ';');
        $_SESSION['chibiweb_ok'] = true;
        session_regenerate_id(true);
        go();
    }
}
if ($action === 'login' && $hasAuth) {
    check_csrf();
    $auth = require $authFile;
    if (password_verify((string) ($_POST['password'] ?? ''), $auth['hash'])) {
        $_SESSION['chibiweb_ok'] = true;
        session_regenerate_id(true);
        go();
    }
    sleep(2); // 総当たり対策（簡易）
    $msg = 'パスワードが違います。';
}
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    go();
}

/* ---------- ログイン後の操作 ---------- */
$channel = mini_channel($_GET['channel'] ?? $_POST['channel'] ?? null);
$dir = MINI_DATA . '/' . $channel;
if ($logged && !is_dir($dir)) mkdir($dir, 0755, true);

if ($logged && $action === 'save') {
    check_csrf();
    $id = (string) ($_POST['id'] ?? '');
    $date = (string) ($_POST['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    if (!mini_valid_id($id)) $id = $date . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
    $meta = [
        'date'   => $date,
        'title'  => trim((string) ($_POST['title'] ?? '')) ?: '(無題)',
        'link'   => trim((string) ($_POST['link'] ?? '')),
        'status' => ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published',
    ];
    file_put_contents($dir . '/' . $id . '.md', mini_serialize($meta, (string) ($_POST['body'] ?? '')), LOCK_EX);
    go('channel=' . $channel . '&saved=1');
}
if ($logged && $action === 'delete') {
    check_csrf();
    $id = (string) ($_POST['id'] ?? '');
    if (mini_valid_id($id) && is_file($dir . '/' . $id . '.md')) {
        // 完全には消さず、_trash へ移す（取り戻せるように）
        $trash = MINI_DATA . '/_trash';
        if (!is_dir($trash)) mkdir($trash, 0755, true);
        rename($dir . '/' . $id . '.md', $trash . '/' . $channel . '--' . $id . '.md');
    }
    go('channel=' . $channel . '&deleted=1');
}

$editing = null;
if ($logged && ($action === 'edit' || $action === 'new')) {
    $editing = ['id' => '', 'date' => date('Y-m-d'), 'title' => '', 'link' => '', 'status' => 'published', 'body' => ''];
    if ($action === 'edit') {
        foreach (mini_list($channel, true) as $it) {
            if ($it['id'] === ($_GET['id'] ?? '')) $editing = $it;
        }
    }
}
if (isset($_GET['saved'])) $msg = '保存しました。';
if (isset($_GET['deleted'])) $msg = 'ごみ箱（data/_trash）に移しました。';
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>お知らせの管理 — <?= h($cfg['site_name']) ?></title>
<style>
:root{--ink:#1f1d1a;--sub:#6b665f;--line:#e4dfd7;--bg:#faf8f4;--card:#fff;--accent:#7a3e2e;--ok:#2f6b4f}
*{box-sizing:border-box}
body{margin:0;font:16px/1.7 system-ui,-apple-system,"Hiragino Sans","Noto Sans JP",sans-serif;color:var(--ink);background:var(--bg)}
main{max-width:640px;margin:0 auto;padding:16px}
header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid var(--line);background:var(--card)}
header b{font-size:15px}
.head-actions{display:flex;align-items:center;gap:12px}
.head-actions a{font-size:14px;white-space:nowrap}
a{color:var(--accent)}
.tabs{display:flex;gap:4px;margin:8px 0 16px;overflow-x:auto}
.tabs a{padding:8px 14px;border-radius:999px;text-decoration:none;color:var(--sub);white-space:nowrap}
.tabs a.on{background:var(--ink);color:#fff}
.btn{display:inline-block;padding:10px 18px;border-radius:8px;border:0;background:var(--accent);color:#fff;font:inherit;text-decoration:none;cursor:pointer}
.btn.sub{background:transparent;color:var(--sub);border:1px solid var(--line)}
.btn.danger{background:transparent;color:#a33;border:1px solid #e6c4c4}
.msg{padding:10px 14px;border-radius:8px;background:#e9f3ee;color:var(--ok);margin-bottom:12px}
.msg.err{background:#f7e8e8;color:#a33}
ul.items{list-style:none;padding:0;margin:16px 0;background:var(--card);border:1px solid var(--line);border-radius:10px}
ul.items li{border-top:1px solid var(--line)}
ul.items li:first-child{border-top:0}
ul.items a{display:block;padding:12px 14px;text-decoration:none;color:inherit}
.date{color:var(--sub);font-size:13px;font-variant-numeric:tabular-nums}
.tag{font-size:12px;padding:1px 8px;border-radius:4px;background:#f1ece4;color:var(--sub);margin-left:6px}
label{display:block;font-size:14px;color:var(--sub);margin:14px 0 4px}
input,textarea,select{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;font:inherit;background:#fff}
textarea{min-height:220px}
.row{display:flex;gap:12px}.row>*{flex:1}
.actions{display:flex;justify-content:space-between;gap:8px;margin-top:20px;flex-wrap:wrap}
.hint{font-size:13px;color:var(--sub)}
.pw{display:flex;gap:8px}.pw input{flex:1}
.pw-toggle{flex:none;padding:0 14px;border:1px solid var(--line);border-radius:8px;background:#fff;font:inherit;font-size:14px;color:var(--sub);cursor:pointer}
.empty{color:var(--sub);text-align:center;padding:32px 0}
</style>
</head>
<body>

<?php if (!$hasAuth && $setupKey === ''): ?>
<main>
  <h1>はじめに</h1>
  <p>まだパスワードを決められません。</p>
  <p>サーバーの <code>news/config.php</code> を開き、<code>'setup_key' =&gt; ''</code> の <code>''</code> の中に、自分だけが知っている合言葉を書いてアップロードしてください。そのあと、このページを開き直すと、パスワードを決められます。</p>
  <p class="hint">知らない人に先にパスワードを決められないようにするためのしくみです。</p>
</main>

<?php elseif (!$hasAuth): ?>
<main>
  <h1>はじめに</h1>
  <p><code>config.php</code> に書いた合言葉と、これから使う管理画面のパスワード（10文字以上）を入れてください。</p>
  <?php if ($msg): ?><p class="msg err"><?= h($msg) ?></p><?php endif ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="action" value="setup">
    <label for="sk">合言葉（config.php の setup_key）</label>
    <input id="sk" type="password" name="setup_key" autocomplete="off" required>
    <input type="text" name="username" value="admin" autocomplete="username" hidden>
    <label for="pw">パスワード</label>
    <div class="pw"><input id="pw" type="password" name="password" autocomplete="new-password" required><button type="button" class="pw-toggle" aria-controls="pw">表示</button></div>
    <p><button class="btn">決定</button></p>
  </form>
</main>

<?php elseif (!$logged): ?>
<main>
  <h1>お知らせの管理</h1>
  <?php if ($msg): ?><p class="msg err"><?= h($msg) ?></p><?php endif ?>
  <p><a href="<?= h($cfg['site_url'] ?? '/') ?>">← サイトにもどる</a></p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="action" value="login">
    <input type="text" name="username" value="admin" autocomplete="username" hidden>
    <label for="pw">パスワード</label>
    <div class="pw"><input id="pw" type="password" name="password" autocomplete="current-password" required autofocus><button type="button" class="pw-toggle" aria-controls="pw">表示</button></div>
    <p><button class="btn">ログイン</button></p>
  </form>
</main>

<?php else: ?>
<header>
  <b><?= h($cfg['site_name']) ?> のお知らせ</b>
  <div class="head-actions">
    <a href="<?= h($cfg['site_url'] ?? '/') ?>">サイトを見る</a>
    <form method="post" style="margin:0">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="logout">
      <button class="btn sub" style="padding:6px 12px">ログアウト</button>
    </form>
  </div>
</header>
<main>
  <nav class="tabs">
    <?php foreach ($cfg['channels'] as $key => $label): ?>
      <a href="?channel=<?= h($key) ?>" class="<?= $key === $channel ? 'on' : '' ?>"><?= h($label) ?></a>
    <?php endforeach ?>
  </nav>
  <?php if ($msg): ?><p class="msg"><?= h($msg) ?></p><?php endif ?>

  <?php if ($editing !== null): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="channel" value="<?= h($channel) ?>">
      <input type="hidden" name="id" value="<?= h($editing['id']) ?>">
      <label for="title">タイトル</label>
      <input id="title" name="title" value="<?= h($editing['title']) ?>" required>
      <div class="row">
        <div>
          <label for="date">日付</label>
          <input id="date" type="date" name="date" value="<?= h($editing['date']) ?>">
        </div>
        <div>
          <label for="status">状態</label>
          <select id="status" name="status">
            <option value="published" <?= $editing['status'] === 'published' ? 'selected' : '' ?>>公開</option>
            <option value="draft" <?= $editing['status'] === 'draft' ? 'selected' : '' ?>>下書き</option>
          </select>
        </div>
      </div>
      <p class="hint">未来の日付にすると、その日まで表示されません（予約公開）。</p>
      <label for="link">リンク先（任意）</label>
      <input id="link" name="link" value="<?= h($editing['link']) ?>" placeholder="https://… または /page.html">
      <label for="body">本文</label>
      <textarea id="body" name="body"><?= h($editing['body']) ?></textarea>
      <p class="hint">空行で段落。**太字**、[文字](URL) でリンク、行頭「- 」で箇条書き。</p>
      <div class="actions">
        <a class="btn sub" href="?channel=<?= h($channel) ?>">もどる</a>
        <button class="btn">保存</button>
      </div>
    </form>
    <?php if ($editing['id']): ?>
      <form method="post" onsubmit="return confirm('このお知らせを削除しますか？（ごみ箱に移ります）')" style="margin-top:32px">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="channel" value="<?= h($channel) ?>">
        <input type="hidden" name="id" value="<?= h($editing['id']) ?>">
        <button class="btn danger">削除</button>
      </form>
    <?php endif ?>

  <?php else: ?>
    <a class="btn" href="?channel=<?= h($channel) ?>&action=new">＋ 新しく書く</a>
    <?php $list = mini_list($channel, true); ?>
    <?php if (!$list): ?>
      <p class="empty">まだお知らせはありません。</p>
    <?php else: ?>
      <ul class="items">
        <?php foreach ($list as $it): ?>
          <li><a href="?channel=<?= h($channel) ?>&action=edit&id=<?= h($it['id']) ?>">
            <span class="date"><?= h($it['date']) ?></span>
            <?php if ($it['status'] === 'draft'): ?><span class="tag">下書き</span><?php endif ?>
            <?php if ($it['status'] === 'published' && $it['date'] > date('Y-m-d')): ?><span class="tag">予約</span><?php endif ?>
            <br><?= h($it['title']) ?>
          </a></li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  <?php endif ?>
  <p class="hint">chibiweb <?= h(MINI_VERSION) ?></p>
</main>
<?php endif ?>
<script>
// パスワードの表示・非表示を切り替える（ユーザー名は不要。パスワード管理アプリ向けに「admin」を隠して送っている）
document.querySelectorAll('.pw-toggle').forEach(function (b) {
  b.addEventListener('click', function () {
    var i = document.getElementById(b.getAttribute('aria-controls'));
    var show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    b.textContent = show ? '隠す' : '表示';
  });
});
</script>
</body>
</html>
