<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/RoutineRepository.php';
require_once __DIR__ . '/../dal/ExerciseRepository.php';
require_once __DIR__ . '/../bll/RoutineValidator.php';

/**
 * POST — create a routine, or replace one you own.
 * Body: { "name": "Push day", "routine_id": 12 (optional), "items": [
 *          { "exercise_id": 3, "sets": 4, "reps": 8, "weight_kg": 60, "rpe": 8 }, ... ] }
 */
requireMethod('POST');
$userId = requireAuth();
$input  = readJsonBody();

// 1. Validate everything before touching the database
try {
    $name  = RoutineValidator::validateName($input['name'] ?? null);
    $items = RoutineValidator::validateItems($input['items'] ?? null);

    $routineId = null;
    if (isset($input['routine_id']) && $input['routine_id'] !== '') {
        $routineId = WorkoutValidator::positiveInt($input['routine_id'], 'routine_id');
    }
} catch (ValidationException $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
}

$routineRepo  = new RoutineRepository();
$exerciseRepo = new ExerciseRepository();

if (!$exerciseRepo->allExist(array_column($items, 'exercise_id'))) {
    jsonResponse(['error' => 'One of the exercises does not exist.'], 400);
}

// 2. Ownership / limits
if ($routineId !== null) {
    // Someone else's routine, or a built-in template → 404 (we don't reveal that it exists)
    if (!$routineRepo->isOwnedBy($routineId, $userId)) {
        jsonResponse(['error' => 'Routine not found.'], 404);
    }
} elseif ($routineRepo->countForUser($userId) >= RoutineValidator::MAX_ROUTINES_PER_USER) {
    jsonResponse(['error' => 'You can keep at most ' . RoutineValidator::MAX_ROUTINES_PER_USER . ' routines. Delete one first.'], 400);
}

// 3. Persist (one transaction inside the repository)
try {
    $savedId = $routineRepo->save($userId, $routineId, $name, $items);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        // UNIQUE (user_id, name): the user already has another routine with this name
        jsonResponse(['error' => 'You already have a routine with that name.', 'code' => 'duplicate_name'], 409);
    }
    throw $e; // the global handler logs it and answers a generic 500
}

jsonResponse(['success' => true, 'routine_id' => $savedId]);
