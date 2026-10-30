<?php
/**
 * Laravel deploy panel.
 * Place at: <project>/public/deploy.php   (the .git folder lives in <project>/, i.e. __DIR__/..)
 */

// ===================== CONFIG =====================
// Generate with:  php8.4 -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
const PASSWORD_HASH = '';

const REPO_URL       = '';               // e.g. https://<token>@github.com/user/repo.git  or  git@github.com:user/repo.git
const GIT_TOKEN      = '';               // GitHub personal access token for private repos over https (not saved in .git/config)
const DEFAULT_BRANCH = 'main';
const PHP_BIN        = '/usr/bin/php8.4';
const COMPOSER_BIN   = '/usr/local/bin/composer';
const GIT_BIN        = '/usr/bin/git';
const MAX_ATTEMPTS   = 5;                // failed logins per IP before lockout
const LOCKOUT_SECS   = 900;
// ==================================================

$ROOT = realpath(__DIR__ . '/..');

// Installed PHP CLI binaries, e.g. ['8.4' => '/usr/bin/php8.4']
$PHP_BINS = [];
foreach (glob('/usr/bin/php[0-9]*.[0-9]*') as $bin) {
    if (preg_match('~/php(\d+\.\d+)$~', $bin, $m) && is_executable($bin)) $PHP_BINS[$m[1]] = $bin;
}
uksort($PHP_BINS, 'version_compare');
$PHP_BINS = array_reverse($PHP_BINS, true);
if (!$PHP_BINS) $PHP_BINS = ['default' => PHP_BIN];

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS'])) ini_set('session.cookie_secure', '1');
session_name('deploy_sess');
session_start();
set_time_limit(0);
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function lockFile() {
    return sys_get_temp_dir() . '/deploy_lock_' . md5($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

function isLocked() {
    $f = lockFile();
    if (!is_file($f)) return false;
    $d = json_decode(file_get_contents($f), true) ?: ['n' => 0, 't' => 0];
    if (time() - $d['t'] > LOCKOUT_SECS) { @unlink($f); return false; }
    return $d['n'] >= MAX_ATTEMPTS;
}

function recordFail() {
    $f = lockFile();
    $d = is_file($f) ? (json_decode(file_get_contents($f), true) ?: ['n' => 0]) : ['n' => 0];
    file_put_contents($f, json_encode(['n' => $d['n'] + 1, 't' => time()]));
}

/** Run a command in the project root and return [exitCode, output]. */
function run($cmd, $cwd) {
    $env = [
        'PATH'          => '/usr/local/bin:/usr/bin:/bin',
        'HOME'          => sys_get_temp_dir(),
        'COMPOSER_HOME' => sys_get_temp_dir() . '/deploy-composer',
        'GIT_TERMINAL_PROMPT' => '0',
    ];
    $p = proc_open($cmd . ' 2>&1', [1 => ['pipe', 'w']], $pipes, $cwd, $env);
    if (!is_resource($p)) return [1, "Could not start process (is proc_open disabled?)\n"];
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return [proc_close($p), $out];
}

function git($args) {
    global $ROOT;
    $auth = '';
    if (GIT_TOKEN !== '') {
        $auth = ' -c ' . escapeshellarg('http.extraHeader=Authorization: Basic ' . base64_encode('x-access-token:' . GIT_TOKEN));
    }
    return GIT_BIN . ' -c safe.directory=' . escapeshellarg($ROOT) . $auth . ' ' . $args;
}

/** Hide the token in anything shown on the page. */
function mask($s) {
    if (GIT_TOKEN === '') return $s;
    return str_replace([base64_encode('x-access-token:' . GIT_TOKEN), GIT_TOKEN], '***', $s);
}

// ---------- auth ----------
$error = '';
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if (empty($_SESSION['auth']) && isset($_POST['password'])) {
    if (PASSWORD_HASH === '') {
        $error = 'Set PASSWORD_HASH in deploy.php first.';
    } elseif (isLocked()) {
        $error = 'Too many failed attempts. Try again later.';
    } elseif (password_verify($_POST['password'], PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        @unlink(lockFile());
    } else {
        recordFail();
        sleep(1);
        $error = 'Wrong password.';
    }
}

if (empty($_SESSION['auth'])) { ?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Deploy Login</title>
<style>
body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
form{background:#1e293b;padding:32px;border-radius:12px;width:300px}
input,button{width:100%;padding:10px;margin-top:12px;border-radius:6px;border:1px solid #334155;box-sizing:border-box;font-size:15px}
input{background:#0f172a;color:#e2e8f0}button{background:#2563eb;color:#fff;border:0;cursor:pointer}
.err{color:#f87171;margin-top:10px}
</style></head><body>
<form method="post"><h2 style="margin:0">Deploy Panel</h2>
<input type="password" name="password" placeholder="Password" autofocus required>
<button>Login</button>
<?php if ($error) echo '<div class="err">' . h($error) . '</div>'; ?>
</form></body></html>
<?php exit; }

// ---------- actions ----------
$log = [];
$isRepo = is_dir($ROOT . '/.git');
$branch = $_POST['branch'] ?? '';
if (!preg_match('~^[A-Za-z0-9._/-]{1,100}$~', $branch) || strpos($branch, '..') !== false) {
    $branch = '';
}

// Selected PHP version (only values from the detected list are accepted)
$phpVer = $_POST['php'] ?? ($_SESSION['php'] ?? array_search(PHP_BIN, $PHP_BINS, true));
if (!isset($PHP_BINS[$phpVer])) $phpVer = array_key_first($PHP_BINS);
$_SESSION['php'] = $phpVer;
$phpBin = $PHP_BINS[$phpVer];

if (isset($_POST['action'])) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $log[] = ['CSRF check', 1, 'Invalid request token. Reload the page.'];
    } else {
        $b = escapeshellarg($branch ?: DEFAULT_BRANCH);
        $php = $phpBin;
        $cmds = [];
        switch ($_POST['action']) {
            case 'clone':
                if ($isRepo) { $log[] = ['Clone', 1, '.git already exists. Use Pull instead.']; break; }
                if (REPO_URL === '') { $log[] = ['Clone', 1, 'Set REPO_URL in deploy.php first.']; break; }
                // Directory is not empty (public/deploy.php exists), so init + fetch instead of git clone
                $cmds = [
                    git('init'),
                    git('remote add origin ' . escapeshellarg(REPO_URL)),
                    git('fetch origin'),
                    git("checkout -f -B $b origin/" . ($branch ?: DEFAULT_BRANCH)),
                ];
                break;
            case 'pull':
                $cmds = [
                    git('fetch origin --prune'),
                    git("checkout $b"),
                    git("pull origin $b"),
                ];
                break;
            case 'status':
                $cmds = [git('status')];
                break;
            case 'log':
                $cmds = [git('log -30 --graph --decorate --date=format:"%Y-%m-%d %H:%M" --format="%h %ad %an%d%n    %s"')];
                break;
            case 'checkout':
                $cmds = [git('fetch origin --prune'), git("checkout $b")];
                break;
            case 'composer_install':
                $cmds = ["$php " . COMPOSER_BIN . ' install --no-interaction --no-dev --optimize-autoloader'];
                break;
            case 'dump':
                $cmds = ["$php " . COMPOSER_BIN . ' dump-autoload -o'];
                break;
            case 'migrate':
                $cmds = ["$php artisan migrate --force"];
                break;
            case 'seed':
                $class = $_POST['seeder'] ?? '';
                if ($class !== '' && !preg_match('~^[A-Za-z0-9_\\\\]{1,150}$~', $class)) {
                    $log[] = ['Seed', 1, 'Invalid seeder class name.']; break;
                }
                $cmds = ["$php artisan db:seed --force" . ($class ? ' --class=' . escapeshellarg($class) : '')];
                break;
            case 'clear':
                $cmds = ["$php artisan optimize:clear"];
                break;
        }
        foreach ($cmds as $c) {
            [$code, $out] = run($c, $ROOT);
            $log[] = [$c, $code, $out];
            if ($code !== 0) break;
        }
        $isRepo = is_dir($ROOT . '/.git');
    }
}

// ---------- state ----------
$current = '';
$branches = [];
if ($isRepo) {
    $current = trim(run(git('rev-parse --abbrev-ref HEAD'), $ROOT)[1]);
    $lastCommit = trim(run(git('log -1 --format="%h %s (%cr, %an)"'), $ROOT)[1]);
    foreach (explode("\n", run(git('branch -r --format="%(refname:short)"'), $ROOT)[1]) as $r) {
        $r = trim($r);
        if ($r === '' || strpos($r, 'HEAD') !== false) continue;
        $branches[] = preg_replace('~^origin/~', '', $r);
    }
    $branches = array_values(array_unique($branches));
}
if (!$branches) $branches = [DEFAULT_BRANCH];
$selected = $branch ?: ($current ?: DEFAULT_BRANCH);
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Deploy Panel</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--line:#334155;--text:#e2e8f0;--muted:#94a3b8}
*{box-sizing:border-box}
body{font-family:system-ui,sans-serif;background:var(--bg);color:var(--text);margin:0;padding:16px}
.wrap{max-width:960px;margin:0 auto}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
header a{color:var(--muted)}
.card{background:var(--card);border-radius:12px;padding:20px;margin-bottom:16px}
.info{color:var(--muted);font-size:14px;line-height:1.7}.info b{color:var(--text)}
.row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:12px}
select,input[type=text]{padding:9px;border-radius:6px;border:1px solid var(--line);background:var(--bg);color:var(--text);font-size:14px;min-width:220px}
button{padding:9px 16px;border:0;border-radius:6px;color:#fff;font-size:14px;cursor:pointer;background:#2563eb}
button.green{background:#16a34a}button.amber{background:#d97706}button.red{background:#dc2626}button.gray{background:#475569}
button:disabled{opacity:.6;cursor:wait}
pre{background:#020617;padding:12px;border-radius:8px;overflow:auto;white-space:pre-wrap;font-size:13px;margin:6px 0 14px}
.ok{color:#4ade80}.fail{color:#f87171}
</style></head><body><div class="wrap">
<header><h2 style="margin:0">Deploy Panel</h2><a href="?logout=1">Logout</a></header>

<div class="card info">
  <div>Project root: <b><?= h($ROOT) ?></b></div>
  <div>Repository: <b><?= $isRepo ? 'yes' : 'not cloned yet' ?></b></div>
  <?php if ($isRepo): ?>
  <div>Current branch: <b><?= h($current) ?></b></div>
  <div>Last commit: <b><?= h($lastCommit) ?></b></div>
  <?php endif; ?>
  <div>PHP: <b><?= h($phpBin) ?></b> &nbsp;(<?= h(trim(run(escapeshellarg($phpBin) . ' -r "echo PHP_VERSION;"', $ROOT)[1])) ?>)</div>
</div>

<form method="post" class="card" onsubmit="return go(event)">
  <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
  <label>Branch</label>
  <div class="row">
    <select name="branch">
      <?php foreach ($branches as $br): ?>
        <option <?= $br === $selected ? 'selected' : '' ?>><?= h($br) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (!$isRepo): ?>
      <button name="action" value="clone" class="green" data-confirm="Clone repository into <?= h($ROOT) ?>?">Clone</button>
    <?php else: ?>
      <button name="action" value="checkout" class="gray" data-confirm="Switch to the selected branch?">Switch Branch</button>
      <button name="action" value="pull" class="green">Git Pull</button>
      <button name="action" value="status" class="gray">Git Status</button>
      <button name="action" value="log" class="gray">Git Log</button>
    <?php endif; ?>
  </div>

  <label style="display:block;margin-top:18px">Composer</label>
  <div class="row">
    <select name="php" title="PHP version used for Composer and Artisan">
      <?php foreach ($PHP_BINS as $ver => $bin): ?>
        <option value="<?= h($ver) ?>" <?= $ver === $phpVer ? 'selected' : '' ?>>PHP <?= h($ver) ?></option>
      <?php endforeach; ?>
    </select>
    <button name="action" value="composer_install">Composer Install</button>
    <button name="action" value="dump">Composer Dump-Autoload</button>
  </div>

  <label style="display:block;margin-top:18px">Artisan</label>
  <div class="row">
    <button name="action" value="migrate" class="amber" data-confirm="Run migrations on the live database?">Migrate</button>
    <input type="text" name="seeder" placeholder="Seeder class (blank = DatabaseSeeder)">
    <button name="action" value="seed" class="red" data-confirm="Run seeder on the live database?">Run Seeder</button>
    <button name="action" value="clear" class="gray">Clear Cache</button>
  </div>
</form>

<?php if ($log): ?>
<div class="card">
  <h3 style="margin-top:0">Output</h3>
  <?php foreach ($log as [$cmd, $code, $out]): ?>
    <div class="<?= $code === 0 ? 'ok' : 'fail' ?>">$ <?= h(mask($cmd)) ?> &nbsp;[exit <?= (int)$code ?>]</div>
    <pre><?= h(mask($out) ?: '(no output)') ?></pre>
  <?php endforeach; ?>
</div>
<?php endif; ?>

</div>
<script>
function go(e) {
  var b = e.submitter;
  if (b && b.dataset.confirm && !confirm(b.dataset.confirm)) return false;
  // keep the clicked button's value, then disable all to prevent double submits
  var hidden = document.createElement('input');
  hidden.type = 'hidden'; hidden.name = 'action'; hidden.value = b ? b.value : '';
  e.target.appendChild(hidden);
  setTimeout(function () {
    e.target.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
    if (b) b.textContent = 'Running...';
  }, 0);
  return true;
}
</script>
</body></html>
