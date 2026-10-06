<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';
require_once __DIR__ . '/../bll/AnalyticsService.php';

$userId = getCurrentUserId();

// Se não tiver login ativo, devolve métricas vazias sem dar erro
if ($userId === null) {
    echo json_encode([
        'weekly_volume'        => [],
        'muscle_group_balance' => [],
        'top_exercises'        => [],
    ]);
    exit;
}

$workoutRepo = new WorkoutRepository();
$analytics = new AnalyticsService($workoutRepo);

echo json_encode([
    'weekly_volume'        => $analytics->getWeeklyVolume($userId, 8),
    'muscle_group_balance' => $analytics->getMuscleGroupBalance($userId, 30),
    'top_exercises'        => $analytics->getTopExercises($userId, 30, 5),
]);