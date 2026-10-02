<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/UserRepository.php';

$userId = getCurrentUserId();
$userRepo = new UserRepository();
$user = $userRepo->findById($userId);

echo json_encode([
    'authenticated' => isAuthenticated(),
    'user'          => $user,
]);