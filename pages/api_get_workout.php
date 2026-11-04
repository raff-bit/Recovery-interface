<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';

$userId = requireAuth();

// filter_var, not (int): "1abc" or id[]=1 is a 400, never silently read as workout 1
$workoutId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($workoutId === false) {
    jsonResponse(['error' => 'Missing or invalid id parameter.'], 400);
}

$workoutRepo = new WorkoutRepository();

$workout = $workoutRepo->getWorkoutById($workoutId, $userId);
if (!$workout) {
    jsonResponse(['error' => 'Workout not found.'], 404);
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

$workout['workout_date'] = isoUtc($workout['workout_date']);

echo json_encode(['workout' => $workout]);
