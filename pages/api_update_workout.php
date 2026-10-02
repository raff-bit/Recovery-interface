<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../dal/WorkoutRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST is supported.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['workout_id']) || empty($input['sets']) || !is_array($input['sets'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Request must include workout_id and a non-empty "sets" array.']);
    exit;
}

$workoutId   = (int) $input['workout_id'];
$workoutDate = $input['workout_date'] ?? null;
$label       = trim($input['label'] ?? '') ?: null;
$notes       = trim($input['notes'] ?? '') ?: null;

$workoutRepo = new WorkoutRepository();

$existing = $workoutRepo->getWorkoutById($workoutId);
if (!$existing) {
    http_response_code(404);
    echo json_encode(['error' => 'Workout not found.']);
    exit;
}

// Keep the original date unless the caller explicitly sent a new one.
$workoutDate = $workoutDate ?: $existing['workout_date'];

try {
    $workoutRepo->beginTransaction();

    $workoutRepo->updateWorkoutMeta($workoutId, $workoutDate, $label, $notes);
    $workoutRepo->deleteSetsForWorkout($workoutId);

    $setNumberByExercise = [];
    $insertedSets = 0;

    foreach ($input['sets'] as $set) {
        if (empty($set['exercise_id']) || !isset($set['reps'])) {
            continue;
        }

        $exerciseId = (int) $set['exercise_id'];
        $setNumberByExercise[$exerciseId] = ($setNumberByExercise[$exerciseId] ?? 0) + 1;

        $workoutRepo->addSet(
            $workoutId,
            $exerciseId,
            $set['set_number'] ?? $setNumberByExercise[$exerciseId],
            (int) $set['reps'],
            isset($set['weight_kg']) ? (float) $set['weight_kg'] : 0,
            isset($set['rpe']) && $set['rpe'] !== '' ? (float) $set['rpe'] : null
        );
        $insertedSets++;
    }

    if ($insertedSets === 0) {
        $workoutRepo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'No valid sets were provided.']);
        exit;
    }

    $workoutRepo->commit();

    echo json_encode([
        'success'     => true,
        'workout_id'  => $workoutId,
        'sets_logged' => $insertedSets,
    ]);
} catch (Exception $e) {
    $workoutRepo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update workout.', 'detail' => $e->getMessage()]);
}