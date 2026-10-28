<?php
/**
 * Fresh install — creates the whole database in one command.
 * ------------------------------------------------------------
 * Usage (from the project root, with .env pointing at an EMPTY database):
 *   php database/install.php
 *
 * Runs: schema.sql → seed.sql → seed_additional_exercises.sql → migrate.php
 * Used by the CI pipeline, and by anyone cloning the repo on a new machine.
 *
 * Refuses to run if the database already has tables: this script is for
 * new installs only. Existing databases are upgraded with migrate.php.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

$existing = $db->query("SHOW TABLES LIKE 'users'")->fetchColumn();
if ($existing !== false) {
    fwrite(STDERR, "The database already has tables — refusing to touch it.\n"
        . "To upgrade an existing database, run: php database/migrate.php\n");
    exit(1);
}

$files = ['schema.sql', 'seed.sql', 'seed_additional_exercises.sql'];

foreach ($files as $file) {
    echo "→ Importing {$file} ... ";
    $sql = file_get_contents(__DIR__ . '/' . $file);

    // The .sql files hard-code "CREATE DATABASE muscle_recovery_v2; USE muscle_recovery_v2;"
    // (handy for phpMyAdmin). Strip both so we install into whatever DB_NAME .env says —
    // that's what lets CI use a separate test database.
    $sql = preg_replace('/^\s*CREATE\s+DATABASE[^;]*;/im', '', $sql);
    $sql = preg_replace('/^\s*USE\s+[`\w]+\s*;/im', '', $sql);

    $db->exec($sql);
    echo "done\n";
}

// Verify the result instead of trusting exec(): with several statements in one
// call, some PHP/MySQL versions only report an error from the FIRST statement.
$muscles   = (int) $db->query('SELECT COUNT(*) FROM muscles')->fetchColumn();
$exercises = (int) $db->query('SELECT COUNT(*) FROM exercises')->fetchColumn();
if ($muscles !== 17 || $exercises < 50) {
    fwrite(STDERR, "Import incomplete: {$muscles} muscles (expected 17), {$exercises} exercises (expected 50+).\n");
    exit(1);
}
echo "  ✓ {$muscles} muscles, {$exercises} exercises\n";

echo "→ Running migrations\n";
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/migrate.php'), $exitCode);
exit($exitCode);