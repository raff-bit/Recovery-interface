<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../dal/WorkoutRepository.php';

$workoutRepo = new WorkoutRepository();

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
$workouts = $workoutRepo->getRecentWorkouts($limit);

if (empty($workouts)) {
    echo json_encode(['workouts' => []]);
    exit;
}

$ids = array_column($workouts, 'id');
$sets = $workoutRepo->getSetsForWorkouts($ids);

$setsByWorkout = [];
foreach ($sets as $s) {
    $setsByWorkout[$s['workout_id']][] = [
        'exercise_name' => $s['exercise_name'],
        'set_number'    => (int) $s['set_number'],
        'reps'          => (int) $s['reps'],
        'weight_kg'     => (float) $s['weight_kg'],
        'rpe'           => $s['rpe'] !== null ? (float) $s['rpe'] : null,
    ];
}

foreach ($workouts as &$w) {
    $w['sets'] = $setsByWorkout[$w['id']] ?? [];
}
unset($w);

echo json_encode(['workouts' => $workouts]);