<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/MuscleRepository.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';
require_once __DIR__ . '/../dal/ExerciseRepository.php';
require_once __DIR__ . '/../dal/ProfileRepository.php';
require_once __DIR__ . '/../bll/RecoveryCalculator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST is supported.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['sets']) || !is_array($input['sets'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Request must include a non-empty "sets" array.']);
    exit;
}

$targetDateInput = $input['target_date'] ?? null;

try {
    $referenceDate = $targetDateInput ? new DateTime($targetDateInput) : new DateTime();
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid target_date.']);
    exit;
}

$hypotheticalSets = [];
foreach ($input['sets'] as $set) {
    if (empty($set['exercise_id'])) {
        continue;
    }
    $hypotheticalSets[] = [
        'exercise_id' => (int) $set['exercise_id'],
        'reps'        => isset($set['reps']) && $set['reps'] !== '' ? (int) $set['reps'] : 10,
        'weight_kg'   => isset($set['weight_kg']) && $set['weight_kg'] !== '' ? (float) $set['weight_kg'] : 0.0,
        'rpe'         => isset($set['rpe']) && $set['rpe'] !== '' ? (float) $set['rpe'] : null,
    ];
}

if (empty($hypotheticalSets)) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid sets were provided.']);
    exit;
}

$userId       = getCurrentUserId();
$muscleRepo   = new MuscleRepository();
$workoutRepo  = new WorkoutRepository();
$exerciseRepo = new ExerciseRepository();
$profileRepo  = new ProfileRepository();
$calculator   = new RecoveryCalculator($muscleRepo, $workoutRepo, $exerciseRepo, $profileRepo);

$preview = $calculator->previewReadiness($hypotheticalSets, $referenceDate, $userId);

echo json_encode([
    'target_date' => $referenceDate->format(DateTime::ATOM),
    'muscles'     => $preview,
]);