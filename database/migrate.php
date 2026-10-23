<?php
/**
 * Migration runner — applies database changes in order, exactly once.
 * ------------------------------------------------------------
 * Usage (from the project root):
 *   php database/migrate.php            apply all pending migrations
 *   php database/migrate.php --status   list applied / pending
 *
 * Migrations live in database/migrations/ and are named NNN_description:
 *   .sql → executed as-is
 *   .php → must `return function (PDO $db): void { ... };`
 *          (for changes that need logic: checks, data conversion)
 *
 * Applied versions are recorded in the schema_migrations table, so every
 * machine (yours, CI, production) converges to the same schema.
 * Golden rule: never edit a migration that was already applied somewhere —
 * write a new one instead.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404); // never runnable from a browser
    exit;
}

require_once __DIR__ . '/../config/database.php';

$db  = Database::getConnection();
$dir = __DIR__ . '/migrations';

$db->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version     VARCHAR(191) PRIMARY KEY,
        applied_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB'
);

$applied = $db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$files = array_values(array_filter(
    scandir($dir),
    fn($f) => preg_match('/^\d{3}_[a-z0-9_]+\.(sql|php)$/', $f)
));
sort($files, SORT_STRING); // 001_, 002_, ... → zero-padding makes string order = numeric order

if (in_array('--status', $argv, true)) {
    foreach ($files as $file) {
        $version = pathinfo($file, PATHINFO_FILENAME);
        echo (isset($applied[$version]) ? "  [x] " : "  [ ] ") . $file . "\n";
    }
    exit(0);
}

$pending = array_filter($files, fn($f) => !isset($applied[pathinfo($f, PATHINFO_FILENAME)]));

if ($pending === []) {
    echo "Database is up to date.\n";
    exit(0);
}

foreach ($pending as $file) {
    $version = pathinfo($file, PATHINFO_FILENAME);
    $path    = "{$dir}/{$file}";
    echo "→ Applying {$file} ... ";

    try {
        if (str_ends_with($file, '.sql')) {
            $db->exec(file_get_contents($path));
        } else {
            $migration = require $path;
            $migration($db);
        }
    } catch (Throwable $e) {
        // Stop at the first failure: later migrations may depend on this one.
        // Note: MySQL auto-commits DDL (CREATE/ALTER), so a half-applied migration
        // can't be rolled back automatically — that's why we back up first.
        echo "FAILED\n\n  " . $e->getMessage() . "\n\n";
        echo "Nothing after {$file} was applied. Fix the problem and run again.\n";
        exit(1);
    }

    $db->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
    echo "done\n";
}

echo "All migrations applied.\n";