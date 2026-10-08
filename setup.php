<?php
ob_start();
/**
 * Local setup check: http://localhost:8888/edoc/setup.php
 * Creates config.php and the database if needed, runs sql/oauth-calendar-migration.sql
 * and shows what is still missing. Safe to run more than once.
 * Remove this file before deploying.
 */
require __DIR__ . '/lib/bootstrap.php';

$checks = [];
function check(string $label, bool $ok, string $detail = ''): void
{
    global $checks;
    $checks[] = [$label, $ok, $detail];
}

// ---- 1. PHP --------------------------------------------------------------
check('PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>='), 'Needs PHP 8.0 or newer (MAMP: Preferences → PHP).');
foreach (['mysqli', 'curl', 'openssl', 'json'] as $ext) {
    check("PHP extension: $ext", extension_loaded($ext));
}

// ---- 2. config.php ---------------------------------------------------------
$cfgFile = __DIR__ . '/config.php';
if (!is_file($cfgFile)) {
    $tpl = file_get_contents(__DIR__ . '/config.example.php');
    $tpl = str_replace("'encryption_key' => ''", "'encryption_key' => '" . bin2hex(random_bytes(32)) . "'", $tpl);
    // Use whatever URL this page was opened on.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8888') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/edoc/setup.php'), '/');
    $tpl = str_replace("'app_url' => 'http://localhost:8888/edoc'", "'app_url' => '" . $base . "'", $tpl);
    if (@file_put_contents($cfgFile, $tpl) === false) {
        check('config.php created', false, 'Could not write config.php, copy config.example.php to config.php manually.');
    } else {
        check('config.php created', true, 'Created with a new random encryption key and app_url = ' . $base);
    }
}
$cfg = is_file($cfgFile) ? require $cfgFile : require __DIR__ . '/config.example.php';
if (is_file($cfgFile) && !preg_match('/^[0-9a-f]{64}$/i', (string)($cfg['encryption_key'] ?? ''))) {
    $raw = file_get_contents($cfgFile);
    $raw = preg_replace("/'encryption_key'\\s*=>\\s*'[^']*'/", "'encryption_key' => '" . bin2hex(random_bytes(32)) . "'", $raw, 1);
    @file_put_contents($cfgFile, $raw);
    $cfg = require $cfgFile;
}
check('Encryption key for stored tokens', (bool)preg_match('/^[0-9a-f]{64}$/i', (string)($cfg['encryption_key'] ?? '')));

// ---- 3. Database -----------------------------------------------------------
$db = $cfg['db'];
mysqli_report(MYSQLI_REPORT_OFF);
$server = @new mysqli($db['host'], $db['user'], $db['password'], '', (int)$db['port']);
$dbOk = !$server->connect_error;
check("MySQL server at {$db['host']}:{$db['port']} (user {$db['user']})", $dbOk,
    $dbOk ? '' : $server->connect_error . ' (is MAMP running? check the port/password in config.php)');

$migrated = [];
if ($dbOk) {
    $name = $server->real_escape_string($db['name']);
    $server->query("CREATE DATABASE IF NOT EXISTS `$name`");
    $server->select_db($db['name']);
    $server->set_charset('utf8mb4');

    $hasBase = $server->query("SHOW TABLES LIKE 'webuser'")->num_rows > 0;
    if (!$hasBase) {
        $sql = file_get_contents(__DIR__ . '/SQL_Database_edoc.sql');
        if ($server->multi_query($sql)) {
            do {
                if ($r = $server->store_result()) {
                    $r->free();
                }
            } while ($server->more_results() && $server->next_result());
        }
        $hasBase = $server->query("SHOW TABLES LIKE 'webuser'")->num_rows > 0;
        check('Imported SQL_Database_edoc.sql (demo data)', $hasBase, $hasBase ? '' : $server->error);
    } else {
        check("Database `{$db['name']}` with eDoc tables", true);
    }

    if ($hasBase) {
        $col = function (string $t, string $c) use ($server): bool {
            return $server->query("SHOW COLUMNS FROM `$t` LIKE '" . $server->real_escape_string($c) . "'")->num_rows > 0;
        };
        $idx = function (string $t, string $i) use ($server): bool {
            return $server->query("SHOW INDEX FROM `$t` WHERE Key_name = '" . $server->real_escape_string($i) . "'")->num_rows > 0;
        };
        $steps = [
            ['webuser.auth_provider', fn() => $col('webuser', 'auth_provider'), "ALTER TABLE webuser ADD COLUMN auth_provider VARCHAR(100) NOT NULL DEFAULT 'password'"],
            ['webuser.google_sub', fn() => $col('webuser', 'google_sub'), "ALTER TABLE webuser ADD COLUMN google_sub VARCHAR(255) NULL"],
            ['webuser.microsoft_sub', fn() => $col('webuser', 'microsoft_sub'), "ALTER TABLE webuser ADD COLUMN microsoft_sub VARCHAR(255) NULL"],
            ['unique google_sub', fn() => $idx('webuser', 'uq_google_sub'), "ALTER TABLE webuser ADD UNIQUE KEY uq_google_sub (google_sub)"],
            ['unique microsoft_sub', fn() => $idx('webuser', 'uq_microsoft_sub'), "ALTER TABLE webuser ADD UNIQUE KEY uq_microsoft_sub (microsoft_sub)"],
            ['patient.ppassword nullable', fn() => false, "ALTER TABLE patient MODIFY ppassword VARCHAR(255) NULL"],
        ];
        foreach ($steps as [$label, $done, $sql]) {
            if (!$done()) {
                $ok = $server->query($sql);
                if ($label !== 'patient.ppassword nullable') {
                    $migrated[] = [$label, (bool)$ok, $ok ? '' : $server->error];
                }
            }
        }
        // The two new tables (CREATE TABLE IF NOT EXISTS in the migration file)
        $file = file_get_contents(__DIR__ . '/sql/oauth-calendar-migration.sql');
        preg_match_all('/CREATE TABLE IF NOT EXISTS.*?;/s', $file, $m);
        foreach ($m[0] as $create) {
            preg_match('/EXISTS\s+(\w+)/', $create, $t);
            $existed = $server->query("SHOW TABLES LIKE '{$t[1]}'")->num_rows > 0;
            $ok = $server->query($create);
            if (!$existed) {
                $migrated[] = ["table {$t[1]}", (bool)$ok, $ok ? '' : $server->error];
            }
        }
        foreach ($migrated as [$l, $ok, $err]) {
            check("Migration: $l", $ok, $err);
        }
        $ready = $server->query("SHOW TABLES LIKE 'calendar_connections'")->num_rows
            && $server->query("SHOW TABLES LIKE 'appointment_calendar_events'")->num_rows
            && $col('webuser', 'google_sub') && $col('webuser', 'microsoft_sub');
        check('Sign-in and calendar tables', (bool)$ready);

        // remove the old untitled test sessions from the demo data (only if nobody booked them)
        $server->query("DELETE FROM schedule WHERE scheduleid BETWEEN 2 AND 8 AND title IN ('1','12')
                          AND scheduledate BETWEEN '2022-06-01' AND '2022-06-30'
                          AND scheduleid NOT IN (SELECT scheduleid FROM appointment WHERE scheduleid IS NOT NULL)");

        if (($_POST['demo_sessions'] ?? '') === '1') {
            // Three test sessions with the demo doctor, next week, for trying booking + rescheduling.
            $tz = new DateTimeZone($cfg['clinic_timezone'] ?? 'Australia/Melbourne');
            $ins = $server->prepare("INSERT INTO schedule (docid, title, scheduledate, scheduletime, nop) VALUES ('1', ?, ?, ?, 10)");
            foreach ([['Morning Clinic', '+7 days', '09:30:00'], ['Afternoon Clinic', '+8 days', '14:00:00'], ['Evening Clinic', '+9 days', '18:00:00']] as [$t, $d, $h]) {
                $day = (new DateTime('today', $tz))->modify($d)->format('Y-m-d');
                $ins->bind_param('sss', $t, $day, $h);
                $ins->execute();
            }
        }
        $future = (int)$server->query("SELECT COUNT(*) c FROM schedule WHERE scheduledate >= CURDATE()")->fetch_assoc()['c'];
        check("Upcoming sessions to book/reschedule into: $future", $future > 1,
            'Click "Add 3 test sessions next week" below (or add sessions as admin@edoc.com) so you can test booking and rescheduling.');
    }
}

// ---- 4. OAuth keys ----------------------------------------------------------
$appUrl = rtrim($cfg['app_url'], '/');
$gOk = !empty($cfg['google_client_id']) && strpos($cfg['google_client_id'], 'PASTE_') !== 0;
$mOk = !empty($cfg['microsoft_client_id']) && strpos($cfg['microsoft_client_id'], 'PASTE_') !== 0;
check('Google client ID/secret in config.php', $gOk, 'Get them from Ajula (or your own Google Cloud project).');
check('Microsoft client ID/secret in config.php', $mOk, 'Get them from Abby (or your own Azure app registration).');
$allOk = !in_array(false, array_column($checks, 1), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/main.css">
    <title>eDoc setup</title>
    <style>
        body{ font-family: Inter, system-ui, sans-serif; background:#f6f8fb; }
        .wrap{ max-width:860px; margin:40px auto; background:#fff; border:1px solid #e6e9ef; border-radius:10px; padding:28px 34px; }
        h1{ font-size:24px; margin:0 0 4px; } h2{ font-size:17px; margin:28px 0 8px; }
        table{ width:100%; border-collapse:collapse; } td{ padding:8px 6px; border-bottom:1px solid #f0f2f5; vertical-align:top; font-size:14px; }
        .ok{ color:#127a3a; font-weight:600; } .no{ color:#b42318; font-weight:600; } .d{ color:#667085; font-size:13px; }
        code{ background:#f2f4f7; padding:2px 6px; border-radius:4px; font-size:13px; word-break:break-all; }
        a.btn{ display:inline-block; background:#0a76d8; color:#fff; padding:10px 18px; border-radius:6px; text-decoration:none; margin-top:16px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>eDoc setup <?php echo $allOk ? '<span class="ok">(all good)</span>' : ''; ?></h1>
    <div class="d">Refresh this page after fixing anything marked "missing".</div>
    <table>
        <?php foreach ($checks as [$label, $ok, $detail]): ?>
            <tr>
                <td width="90"><?php echo $ok ? '<span class="ok">OK</span>' : '<span class="no">missing</span>'; ?></td>
                <td><?php echo e($label); ?><?php if (!$ok && $detail || $ok && $detail && strpos($label, 'created') !== false): ?><div class="d"><?php echo e($detail); ?></div><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Redirect URIs to register (must match exactly)</h2>
    <table>
        <tr><td width="190">Google Cloud Console</td><td><code><?php echo e($appUrl); ?>/oauth-callback.php</code></td></tr>
        <tr><td>Azure App registration</td><td><code><?php echo e($appUrl); ?>/microsoft-callback.php</code></td></tr>
    </table>
    <div class="d" style="margin-top:8px">Google: also enable <b>Google Calendar API</b> and add the scope
        <code>.../auth/calendar.events</code> on the consent screen. Microsoft: add the delegated Graph permissions
        <code>offline_access</code> and <code>Calendars.ReadWrite</code>.</div>

    <form method="POST" style="display:inline"><input type="hidden" name="demo_sessions" value="1">
        <button class="btn" style="border:0;cursor:pointer;background:#e8f1fc;color:#0a76d8;font-size:15px">Add 3 test sessions next week</button></form>
    <a class="btn" href="login.php">Open the app</a>
</div>
</body>
</html>
