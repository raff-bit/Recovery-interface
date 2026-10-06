<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/ProfileRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST is supported.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$allowedSex = ['male', 'female', 'unspecified'];
$allowedActivity = ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'athlete'];

$sex = in_array($input['sex'] ?? '', $allowedSex, true) ? $input['sex'] : 'unspecified';
$activityLevel = in_array($input['activity_level'] ?? '', $allowedActivity, true) ? $input['activity_level'] : 'moderately_active';
$heightCm = isset($input['height_cm']) && $input['height_cm'] !== '' ? (float) $input['height_cm'] : null;
$bodyWeightKg = isset($input['body_weight_kg']) ? (float) $input['body_weight_kg'] : 0;
$age = isset($input['age']) && $input['age'] !== '' ? (int) $input['age'] : null;

if ($bodyWeightKg <= 0 || $bodyWeightKg > 400) {
    http_response_code(400);
    echo json_encode(['error' => 'body_weight_kg must be a realistic positive number.']);
    exit;
}

if ($age !== null && ($age < 12 || $age > 120)) {
    http_response_code(400);
    echo json_encode(['error' => 'Age must be between 12 and 120.']);
    exit;
}

$userId = requireAuth();
$profileRepo = new ProfileRepository();
$profileRepo->saveProfile($userId, $sex, $heightCm, $bodyWeightKg, $age, $activityLevel);

echo json_encode(['success' => true, 'profile' => $profileRepo->getProfile($userId)]);