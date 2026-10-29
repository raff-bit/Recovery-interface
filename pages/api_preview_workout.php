<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/MuscleRepository.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';
require_once __DIR__ . '/../dal/ExerciseRepository.php';
require_once __DIR__ . '/../dal/ProfileRepository.php';
require_once __DIR__ . '/../bll/RecoveryCalculator.php';
require_once __DIR__ . '/../bll/WorkoutValidator.php';

requireMethod('POST');
$input = readJsonBody();   // capped at 64 KB

// The planner is open to guests too, so it gets exactly the same limits as
// logging a real workout: at most 100 sets, strictly typed values. Without this,
// an anonymous request could ask for millions of hypothetical sets.
try {
    $sets = WorkoutValidator::validateSets($input['sets'] ?? null);
} catch (ValidationException $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
}

// target_date: optional, a calendar day "YYYY-MM-DD" (what <input type="date"> sends)
$referenceDate = new DateTime();
$targetDate = $input['target_date'] ?? null;
if ($targetDate !== null && $targetDate !== '') {
    $parsed = is_string($targetDate) ? DateTime::createFromFormat('!Y-m-d', $targetDate) : false;
    $year   = $parsed ? (int) $parsed->format('Y') : 0;
    if ($parsed === false || $parsed->format('Y-m-d') !== $targetDate || $year < 2000 || $year > 2100) {
        jsonResponse(['error' => 'target_date must be a valid date in YYYY-MM-DD format.'], 400);
    }
    $referenceDate = $parsed;
}

$userId       = getCurrentUserId();
$muscleRepo   = new MuscleRepository();
$workoutRepo  = new WorkoutRepository();
$exerciseRepo = new ExerciseRepository();
$profileRepo  = new ProfileRepository();
$calculator   = new RecoveryCalculator($muscleRepo, $workoutRepo, $exerciseRepo, $profileRepo);

$preview = $calculator->previewReadiness($sets, $referenceDate, $userId);

jsonResponse([
    'target_date' => $referenceDate->format(DateTime::ATOM),
    'muscles'     => $preview,
]);
