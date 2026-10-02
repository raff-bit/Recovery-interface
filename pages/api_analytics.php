<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../dal/WorkoutRepository.php';
require_once __DIR__ . '/../bll/AnalyticsService.php';

$workoutRepo = new WorkoutRepository();
$analytics = new AnalyticsService($workoutRepo);

echo json_encode([
    'weekly_volume'        => $analytics->getWeeklyVolume(8),
    'muscle_group_balance' => $analytics->getMuscleGroupBalance(30),
    'top_exercises'        => $analytics->getTopExercises(30, 5),
]);