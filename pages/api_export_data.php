<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';

$userId = getCurrentUserId();
$format = strtolower($_GET['format'] ?? 'json');
$workoutRepo = new WorkoutRepository();

$workouts = $workoutRepo->getAllWorkoutsForExport($userId);
$ids = array_column($workouts, 'id');
$allSets = $workoutRepo->getSetsForWorkouts($ids);

$setsByWorkout = [];
foreach ($allSets as $s) {
    $setsByWorkout[$s['workout_id']][] = $s;
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="recovery-interface-export-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['workout_date', 'label', 'notes', 'exercise_name', 'set_number', 'reps', 'weight_kg', 'rpe']);

    foreach ($workouts as $w) {
        $sets = $setsByWorkout[$w['id']] ?? [];

        if (empty($sets)) {
            fputcsv($out, [$w['workout_date'], $w['label'], $w['notes'], '', '', '', '', '']);
            continue;
        }

        foreach ($sets as $s) {
            fputcsv($out, [
                $w['workout_date'],
                $w['label'],
                $w['notes'],
                $s['exercise_name'],
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
        'workout_date' => $w['workout_date'],
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