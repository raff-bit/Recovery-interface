<?php
/**
 * Migration 003 — convert existing workout dates to UTC
 * ------------------------------------------------------------
 * Until now, workout_date was written with PHP's date(), i.e. in whatever
 * timezone php.ini had (XAMPP ships "Europe/Berlin"). From now on everything
 * is stored in UTC (config/env.php + config/database.php), so old rows must
 * be shifted to match — otherwise every old workout would appear 1–2 h off
 * and the recovery math would treat it as more/less recent than it was.
 *
 * Why PHP and not one SQL "UPDATE ... - INTERVAL 1 HOUR": the offset is NOT
 * constant. Berlin is UTC+2 in summer and UTC+1 in winter, so a July workout
 * needs -2 h and a January one -1 h. PHP's DateTime knows every DST rule.
 *
 * Source timezone: LEGACY_TIMEZONE env var if set, otherwise php.ini's
 * date.timezone (the value the old code actually used). Empty/UTC → nothing to do.
 *
 * Preview without changing anything:
 *   DRY_RUN=1 php database/migrate.php
 */

return function (PDO $db): void {
    $legacyName = getenv('LEGACY_TIMEZONE') ?: (ini_get('date.timezone') ?: 'UTC');
    $dryRun     = (bool) getenv('DRY_RUN');

    try {
        $legacyTz = new DateTimeZone($legacyName);
    } catch (Exception $e) {
        throw new RuntimeException("Unknown timezone '{$legacyName}'. Set LEGACY_TIMEZONE, e.g. LEGACY_TIMEZONE=Europe/Lisbon");
    }
    $utc = new DateTimeZone('UTC');

    echo "\n    Legacy timezone: {$legacyName}" . ($dryRun ? '  (DRY RUN — nothing will be changed)' : '') . "\n";

    $rows = $db->query('SELECT id, workout_date FROM workouts ORDER BY workout_date')->fetchAll();

    if ($legacyTz->getName() === 'UTC' || $rows === []) {
        echo "    Nothing to convert.\n    ";
    } else {
        $converted = [];
        foreach ($rows as $row) {
            // Interpret the stored text in the OLD timezone, then express it in UTC.
            // (During the yearly "fall back" hour, 02:00–03:00 happens twice; PHP
            //  picks the first occurrence — at most 1 h off for a workout logged
            //  in the middle of the night on that one day.)
            $local = new DateTimeImmutable($row['workout_date'], $legacyTz);
            $converted[(int) $row['id']] = $local->setTimezone($utc)->format('Y-m-d H:i:s');
        }

        // Show a few real examples so you can sanity-check before trusting it
        $samples = array_slice($rows, -3);
        foreach ($samples as $row) {
            printf("    #%d  %s (%s)  →  %s UTC\n", $row['id'], $row['workout_date'], $legacyName, $converted[(int) $row['id']]);
        }
        printf("    %d workout(s) to convert.\n    ", count($rows));

        if ($dryRun) {
            // Throwing stops the runner BEFORE it records 003 as applied
            throw new RuntimeException('Dry run finished — nothing was changed. Run again without DRY_RUN to apply.');
        }

        // One transaction: either every row is converted, or none is
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE workouts SET workout_date = ? WHERE id = ?');
            foreach ($converted as $id => $utcDate) {
                $stmt->execute([$utcDate, $id]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    if ($dryRun) {
        // Also reached when there was nothing to convert: still must not be recorded as applied
        throw new RuntimeException('Dry run finished — nothing was changed. Run again without DRY_RUN to apply.');
    }

    // Throttle rows were timestamped with MySQL's local NOW(); they only matter
    // for 15–60 minutes anyway, so start clean instead of mixing timezones.
    $db->exec('DELETE FROM auth_attempts');
};