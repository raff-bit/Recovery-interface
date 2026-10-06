<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';

if (empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id parameter.']);
    exit;
}

$userId = requireAuth();
$workoutId = (int) $_GET['id'];
$workoutRepo = new WorkoutRepository();

$workout = $workoutRepo->getWorkoutById($workoutId, $userId);
if (!$workout) {
    http_response_code(404);
    echo json_encode(['error' => 'Workout not found.']);
    exit;
}

$sets = $workoutRepo->getSetsForWorkouts([$workoutId]);

$workout['sets'] = array_map(function ($s) {
    return [
        'exercise_id'   => (int) $s['exercise_id'],
        'exercise_name' => $s['exercise_name'],
        'set_number'    => (int) $s['set_number'],
        'reps'          => (int) $s['reps'],
        'weight_kg'     => (float) $s['weight_kg'],
        'rpe'           => $s['rpe'] !== null ? (float) $s['rpe'] : null,
    ];
}, $sets);

echo json_encode(['workout' => $workout]);