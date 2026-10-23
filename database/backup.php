<?php
/**
 * Database backup — the RIGHT way (logical dump, not copying data folders).
 * ------------------------------------------------------------
 * Usage (from the project root):  php database/backup.php
 * Output: backups/<db>_YYYY-mm-dd_HHMMSS.sql.gz  (keeps the newest 14)
 *
 * Why not copy C:\xampp\mysql\data? InnoDB keeps data in memory and in
 * shared files (ibdata1, redo logs); a copied folder taken while MySQL runs
 * is often inconsistent — that's how the Aria corruption lost data before.
 * mysqldump --single-transaction reads a consistent snapshot while the app
 * keeps running, and produces plain SQL you can restore anywhere.
 *
 * Restore:
 *   gunzip -c backups/FILE.sql.gz | /c/xampp/mysql/bin/mysql -u root muscle_recovery_v2
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/env.php';
loadEnv(__DIR__ . '/../.env');

const KEEP_LAST = 14;

$dbName = env('DB_NAME', 'muscle_recovery_v2');
$args = [
    '--host=' . env('DB_HOST', 'localhost'),
    '--port=' . env('DB_PORT', '3306'),
    '--user=' . env('DB_USER', 'root'),
    '--single-transaction',          // consistent snapshot without locking tables
    '--routines', '--triggers',
    '--default-character-set=utf8mb4',
    '--no-tablespaces',              // avoids needing the PROCESS privilege on MySQL 8
    $dbName,
];

// Find mysqldump: explicit env var → XAMPP default → whatever is on PATH
$candidates = array_filter([env('MYSQLDUMP_PATH'), 'C:\\xampp\\mysql\\bin\\mysqldump.exe', '/c/xampp/mysql/bin/mysqldump']);
$mysqldump = 'mysqldump';
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $mysqldump = $candidate;
        break;
    }
}

$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) {
    fwrite(STDERR, "Cannot create {$backupDir}\n");
    exit(1);
}

$file = sprintf('%s/%s_%s.sql.gz', $backupDir, $dbName, date('Y-m-d_His'));

// Password goes through the environment, NOT the command line
// (command lines are visible to every process on the machine)
$env = getenv();
$env['MYSQL_PWD'] = env('DB_PASS', '');

$cmd = array_merge([$mysqldump], $args);
$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
if (!is_resource($proc)) {
    fwrite(STDERR, "Could not start mysqldump. Set MYSQLDUMP_PATH in .env to its full path.\n");
    exit(1);
}

// Stream straight into gzip — memory use stays flat no matter how big the DB gets
$gz = gzopen($file, 'wb6');
$bytes = 0;
while (!feof($pipes[1])) {
    $chunk = fread($pipes[1], 1 << 16);
    if ($chunk !== false && $chunk !== '') {
        gzwrite($gz, $chunk);
        $bytes += strlen($chunk);
    }
}
gzclose($gz);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($proc);

// A dump that failed half-way is worse than no dump: it looks like a backup
if ($exitCode !== 0 || $bytes === 0) {
    @unlink($file);
    fwrite(STDERR, "Backup FAILED (exit {$exitCode}):\n{$stderr}\n");
    exit(1);
}

// Retention: keep only the newest KEEP_LAST backups
$all = glob("{$backupDir}/{$dbName}_*.sql.gz");
rsort($all, SORT_STRING); // timestamped names → string order = time order
foreach (array_slice($all, KEEP_LAST) as $old) {
    unlink($old);
}

printf("Backup OK → %s (%.1f KB, %.1f KB uncompressed)\n", basename($file), filesize($file) / 1024, $bytes / 1024);