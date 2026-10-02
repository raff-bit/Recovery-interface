<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../dal/MuscleRepository.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';
require_once __DIR__ . '/../dal/ExerciseRepository.php';
require_once __DIR__ . '/../dal/ProfileRepository.php';
require_once __DIR__ . '/../bll/RecoveryCalculator.php';

$muscleRepo   = new MuscleRepository();
$workoutRepo  = new WorkoutRepository();
$exerciseRepo = new ExerciseRepository();
$profileRepo  = new ProfileRepository();
$calculator   = new RecoveryCalculator($muscleRepo, $workoutRepo, $exerciseRepo, $profileRepo);

echo json_encode([
    'muscles'      => $calculator->getAllMuscleReadiness(),
    'generated_at' => (new DateTime())->format(DateTime::ATOM),
]);