<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/RoutineRepository.php';
require_once __DIR__ . '/../bll/RoutineValidator.php';

/**
 * GET — the caller's routines + the built-in templates.
 * Guests get the templates only (they can still use them in the planner).
 */
requireMethod('GET');

$routineRepo = new RoutineRepository();

jsonResponse([
    'routines'     => $routineRepo->listForUser(getCurrentUserId()),
    'max_routines' => RoutineValidator::MAX_ROUTINES_PER_USER,
]);
