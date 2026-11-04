<?php

require_once __DIR__ . '/XlsxWriter.php';

/**
 * TrainingExport — turns a user's workouts into the Excel export.
 * ------------------------------------------------------------
 * Business rules live here (the endpoint only fetches and sends):
 *   - Dates are stored in UTC; the spreadsheet shows them in the USER's time zone,
 *     sent by the browser (Intl API) and accepted only if PHP knows that zone.
 *   - Weights are always kg (the stored unit), so the numbers match the JSON export.
 *   - Volume = reps × weight (external load). Bodyweight-only sets count as 0 here.
 *
 * Sheet "Sessions": one row per workout · Sheet "Sets": one row per set.
 */
final class TrainingExport
{
    /** A time zone from the browser, or UTC if it's missing or not a real IANA zone. */
    public static function resolveTimezone(mixed $requested): DateTimeZone
    {
        if (is_string($requested) && in_array($requested, DateTimeZone::listIdentifiers(), true)) {
            return new DateTimeZone($requested);
        }
        return new DateTimeZone('UTC');
    }

    /** DB value "2026-11-04 18:30:00" (UTC) → the same instant in $tz. */
    public static function toLocal(string $utcDatetime, DateTimeZone $tz): DateTimeImmutable
    {
        return (new DateTimeImmutable($utcDatetime, new DateTimeZone('UTC')))->setTimezone($tz);
    }

    /**
     * @param list<array<string, mixed>> $workouts            rows from getAllWorkoutsForExport()
     * @param array<int|string, list<array<string, mixed>>> $setsByWorkout sets grouped by workout id
     * @return list<list<mixed>>
     */
    public static function sessionRows(array $workouts, array $setsByWorkout, DateTimeZone $tz): array
    {
        $rows = [];
        foreach ($workouts as $w) {
            $sets = $setsByWorkout[$w['id']] ?? [];
            $volume = 0.0;
            $exercises = [];
            foreach ($sets as $s) {
                $volume += (int) $s['reps'] * (float) $s['weight_kg'];
                $exercises[$s['exercise_id']] = true;
            }
            $rows[] = [
                self::toLocal((string) $w['workout_date'], $tz),
                $w['label'],
                count($exercises),
                count($sets),
                round($volume, 2),
                $w['notes'],
            ];
        }
        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $workouts
     * @param array<int|string, list<array<string, mixed>>> $setsByWorkout
     * @return list<list<mixed>>
     */
    public static function setRows(array $workouts, array $setsByWorkout, DateTimeZone $tz): array
    {
        $rows = [];
        foreach ($workouts as $w) {
            $date = self::toLocal((string) $w['workout_date'], $tz);
            foreach ($setsByWorkout[$w['id']] ?? [] as $s) {
                $reps   = (int) $s['reps'];
                $weight = (float) $s['weight_kg'];
                $rows[] = [
                    $date,
                    $w['label'],
                    $s['exercise_name'],
                    (int) $s['set_number'],
                    $reps,
                    $weight,
                    $s['rpe'] !== null ? (float) $s['rpe'] : null,
                    round($reps * $weight, 2),
                ];
            }
        }
        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $workouts
     * @param array<int|string, list<array<string, mixed>>> $setsByWorkout
     */
    public static function toXlsx(array $workouts, array $setsByWorkout, DateTimeZone $tz): string
    {
        $book = new XlsxWriter();
        $book->addSheet('Sessions', [
            ['Date', 'datetime', 17],
            ['Session', 'text', 24],
            ['Exercises', 'number', 10],
            ['Sets', 'number', 8],
            ['Volume (kg)', 'number', 12],
            ['Notes', 'text', 40],
        ], self::sessionRows($workouts, $setsByWorkout, $tz));

        $book->addSheet('Sets', [
            ['Date', 'datetime', 17],
            ['Session', 'text', 24],
            ['Exercise', 'text', 26],
            ['Set', 'number', 6],
            ['Reps', 'number', 7],
            ['Weight (kg)', 'number', 12],
            ['RPE', 'number', 6],
            ['Volume (kg)', 'number', 12],
        ], self::setRows($workouts, $setsByWorkout, $tz));

        return $book->toBinary();
    }
}
