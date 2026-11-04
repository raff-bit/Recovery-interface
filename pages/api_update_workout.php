<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';
require_once __DIR__ . '/../dal/ExerciseRepository.php';
require_once __DIR__ . '/../bll/WorkoutValidator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST is supported.']);
    exit;
}

$userId = requireAuth();

$input = readJsonBody();   // JSON object, capped at 64 KB (a 100-set workout is ~8 KB)

$workoutId = filter_var($input['workout_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($workoutId === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Request must include a valid workout_id.']);
    exit;
}

$workoutRepo = new WorkoutRepository();

// Ownership check: a user can only edit their own workouts (returns 404, not 403,
// so we don't reveal that someone else's workout with this ID exists)
$existing = $workoutRepo->getWorkoutById($workoutId, $userId);
if (!$existing) {
    http_response_code(404);
    echo json_encode(['error' => 'Workout not found.']);
    exit;
}

try {
    $sets        = WorkoutValidator::validateSets($input['sets'] ?? null);
    $workoutDate = WorkoutValidator::validateDate($input['workout_date'] ?? null, $existing['workout_date']);
    $label       = WorkoutValidator::optionalText($input['label'] ?? null, WorkoutValidator::MAX_LABEL_LENGTH, 'label');
    $notes       = WorkoutValidator::optionalText($input['notes'] ?? null, WorkoutValidator::MAX_NOTES_LENGTH, 'notes');
} catch (ValidationException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

if (!(new ExerciseRepository())->allExist(array_column($sets, 'exercise_id'))) {
    jsonResponse(['error' => 'One of the exercises does not exist.'], 400);
}

try {
    $workoutRepo->beginTransaction();

    $workoutRepo->updateWorkoutMeta($workoutId, $userId, $workoutDate, $label, $notes);
    $workoutRepo->deleteSetsForWorkout($workoutId, $userId);
    $workoutRepo->addSets($workoutId, $sets);

    $workoutRepo->commit();

    echo json_encode([
        'success'     => true,
        'workout_id'  => $workoutId,
        'sets_logged' => count($sets),
    ]);
} catch (Throwable $e) {
    $workoutRepo->rollBack();
    error_log('api_update_workout error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update workout. Please try again.']);
}
