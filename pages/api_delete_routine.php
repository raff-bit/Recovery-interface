<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/RoutineRepository.php';

/** POST { "routine_id": 12 } — deletes one of YOUR routines. Templates can't be deleted. */
requireMethod('POST');
$userId = requireAuth();
$input  = readJsonBody();

$routineId = filter_var($input['routine_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($routineId === false || is_bool($input['routine_id'] ?? null)) {
    jsonResponse(['error' => 'Request must include a valid routine_id.'], 400);
}

$routineRepo = new RoutineRepository();

// 404 (not 403) for someone else's routine or a template: we don't reveal what exists
if (!$routineRepo->isOwnedBy($routineId, $userId)) {
    jsonResponse(['error' => 'Routine not found.'], 404);
}

$routineRepo->delete($routineId, $userId);

jsonResponse(['success' => true, 'deleted_id' => $routineId]);
