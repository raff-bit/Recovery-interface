<?php
/**
 * GET pages/api_export_data.php?format=xlsx|json|csv[&tz=Europe/Lisbon]
 * Downloads the signed-in user's full training history.
 *   xlsx → Excel workbook: "Sessions" + "Sets" sheets, dates in the user's time zone
 *   json → complete raw copy (dates in UTC, ISO 8601)
 *   csv  → kept for compatibility; one row per set
 * Cache-Control: no-store is already sent for every API response (config/bootstrap.php).
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';
require_once __DIR__ . '/../bll/TrainingExport.php';

$userId = requireAuth();

$format = is_string($_GET['format'] ?? null) ? strtolower($_GET['format']) : 'json';
if (!in_array($format, ['json', 'csv', 'xlsx'], true)) {
    jsonResponse(['error' => 'Unknown format. Use xlsx, json or csv.'], 400);
}

$workoutRepo = new WorkoutRepository();
$workouts = $workoutRepo->getAllWorkoutsForExport($userId);
$ids = array_column($workouts, 'id');
$allSets = $workoutRepo->getSetsForWorkouts($ids);

$setsByWorkout = [];
foreach ($allSets as $s) {
    $setsByWorkout[$s['workout_id']][] = $s;
}

if ($format === 'xlsx') {
    $tz = TrainingExport::resolveTimezone($_GET['tz'] ?? null);
    $file = TrainingExport::toXlsx($workouts, $setsByWorkout, $tz);
    $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="recovery-interface-export-' . $today . '.xlsx"');
    header('Content-Length: ' . strlen($file));
    echo $file;
    exit;
}

/**
 * CSV cells starting with = + - @ (or tab / carriage return) are run as FORMULAS by Excel:
 * a session named =HYPERLINK("http://evil","click") would become a live link.
 * Prefixing a ' makes Excel show the text as-is (OWASP "CSV injection").
 */
function csvText(?string $value): ?string
{
    if ($value !== null && $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
        return "'" . $value;
    }
    return $value;
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="recovery-interface-export-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['workout_date', 'label', 'notes', 'exercise_name', 'set_number', 'reps', 'weight_kg', 'rpe']);

    foreach ($workouts as $w) {
        $sets = $setsByWorkout[$w['id']] ?? [];
        $label = csvText($w['label']);
        $notes = csvText($w['notes']);

        if (empty($sets)) {
            fputcsv($out, [isoUtc($w['workout_date']), $label, $notes, '', '', '', '', '']);
            continue;
        }

        foreach ($sets as $s) {
            fputcsv($out, [
                isoUtc($w['workout_date']),
                $label,
                $notes,
                csvText($s['exercise_name']),
                $s['set_number'],
                $s['reps'],
                $s['weight_kg'],
                $s['rpe'],
            ]);
        }
    }

    fclose($out);
    exit;
}

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="recovery-interface-export-' . date('Y-m-d') . '.json"');

$exportWorkouts = array_map(function ($w) use ($setsByWorkout) {
    $sets = $setsByWorkout[$w['id']] ?? [];
    return [
        'workout_date' => isoUtc($w['workout_date']),
        'label'        => $w['label'],
        'notes'        => $w['notes'],
        'sets'         => array_map(function ($s) {
            return [
                'exercise_name' => $s['exercise_name'],
                'set_number'    => (int) $s['set_number'],
                'reps'          => (int) $s['reps'],
                'weight_kg'     => (float) $s['weight_kg'],
                'rpe'           => $s['rpe'] !== null ? (float) $s['rpe'] : null,
            ];
        }, $sets),
    ];
}, $workouts);

echo json_encode([
    'exported_at' => (new DateTime())->format(DateTime::ATOM),
    'workouts'    => $exportWorkouts,
], JSON_PRETTY_PRINT);
