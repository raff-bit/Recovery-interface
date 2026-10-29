<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/ProfileRepository.php';

requireMethod('POST');
$userId = requireAuth();
$input  = readJsonBody();

$allowedSex      = ['male', 'female', 'unspecified'];
$allowedActivity = ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'athlete'];

$sex           = in_array($input['sex'] ?? '', $allowedSex, true) ? $input['sex'] : 'unspecified';
$activityLevel = in_array($input['activity_level'] ?? '', $allowedActivity, true) ? $input['activity_level'] : 'moderately_active';

// filter_var instead of (float)/(int) casts: "abc" or an array is rejected, never silently turned into 0 or 1
$bodyWeightKg = filter_var($input['body_weight_kg'] ?? null, FILTER_VALIDATE_FLOAT);
if ($bodyWeightKg === false || $bodyWeightKg <= 0 || $bodyWeightKg > 400) {
    jsonResponse(['error' => 'Body weight must be a number between 0 and 400 kg.'], 400);
}

$heightCm = null;
if (isset($input['height_cm']) && $input['height_cm'] !== '') {
    $heightCm = filter_var($input['height_cm'], FILTER_VALIDATE_FLOAT);
    if ($heightCm === false || $heightCm < 50 || $heightCm > 272) {
        jsonResponse(['error' => 'Height must be between 50 and 272 cm.'], 400);
    }
}

$age = null;
if (isset($input['age']) && $input['age'] !== '') {
    $age = filter_var($input['age'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 12, 'max_range' => 120]]);
    if ($age === false) {
        jsonResponse(['error' => 'Age must be a whole number between 12 and 120.'], 400);
    }
}

$profileRepo = new ProfileRepository();
$profileRepo->saveProfile($userId, $sex, $heightCm, $bodyWeightKg, $age, $activityLevel);

jsonResponse(['success' => true, 'profile' => $profileRepo->getProfile($userId)]);
