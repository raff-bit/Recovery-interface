<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../dal/WorkoutRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST is supported.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['workout_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Request must include workout_id.']);
    exit;
}

$workoutRepo = new WorkoutRepository();
$workoutId = (int) $input['workout_id'];

$existing = $workoutRepo->getWorkoutById($workoutId);
if (!$existing) {
    http_response_code(404);
    echo json_encode(['error' => 'Workout not found.']);
    exit;
}

$workoutRepo->deleteWorkout($workoutId);

echo json_encode(['success' => true, 'deleted_id' => $workoutId]);