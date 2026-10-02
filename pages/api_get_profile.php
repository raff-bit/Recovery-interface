<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../dal/ProfileRepository.php';

$profileRepo = new ProfileRepository();
$profile = $profileRepo->getProfile();

echo json_encode(['profile' => $profile]);