<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';

requireMethod('POST');
$userId = requireAuth();
$input  = readJsonBody();

$workoutId = filter_var($input['workout_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($workoutId === false) {
    jsonResponse(['error' => 'Request must include a valid workout_id.'], 400);
}

$workoutRepo = new WorkoutRepository();

// Ownership check: 404 (not 403) so we don't reveal that someone else's workout exists
if (!$workoutRepo->getWorkoutById($workoutId, $userId)) {
    jsonResponse(['error' => 'Workout not found.'], 404);
}

$workoutRepo->deleteWorkout($workoutId, $userId);

jsonResponse(['success' => true, 'deleted_id' => $workoutId]);
