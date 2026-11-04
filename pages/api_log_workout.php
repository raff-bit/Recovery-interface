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

// Auth first: anonymous callers learn nothing about our validation rules
$userId = requireAuth();

$input = readJsonBody();   // JSON object, capped at 64 KB (a 100-set workout is ~8 KB)

// 1. Validate EVERYTHING before touching the database
try {
    $sets        = WorkoutValidator::validateSets($input['sets'] ?? null);
    $workoutDate = WorkoutValidator::validateDate($input['workout_date'] ?? null, date('Y-m-d H:i:s'));
    $label       = WorkoutValidator::optionalText($input['label'] ?? null, WorkoutValidator::MAX_LABEL_LENGTH, 'label');
    $notes       = WorkoutValidator::optionalText($input['notes'] ?? null, WorkoutValidator::MAX_NOTES_LENGTH, 'notes');
} catch (ValidationException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// An unknown exercise is the CLIENT's mistake → 400, not a foreign-key failure logged as a 500
if (!(new ExerciseRepository())->allExist(array_column($sets, 'exercise_id'))) {
    jsonResponse(['error' => 'One of the exercises does not exist.'], 400);
}

// 2. Persist atomically — either the whole workout is saved, or nothing is
$workoutRepo = new WorkoutRepository();

try {
    $workoutRepo->beginTransaction();

    $workoutId = $workoutRepo->createWorkout($userId, $workoutDate, $label, $notes);
        $workoutRepo->addSets($workoutId, $sets);

    $workoutRepo->commit();

    echo json_encode([
        'success'     => true,
        'workout_id'  => $workoutId,
        'sets_logged' => count($sets),
    ]);
} catch (Throwable $e) {
    // Throwable (not Exception) also catches TypeErrors — nothing escapes as a raw stack trace
    $workoutRepo->rollBack();
    error_log('api_log_workout error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to log workout. Please try again.']);
}