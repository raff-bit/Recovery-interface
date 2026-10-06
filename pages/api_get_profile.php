<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/ProfileRepository.php';

$userId = getCurrentUserId();

// Se não estiver autenticado, devolve perfil nulo sem erro
if ($userId === null) {
    echo json_encode(['profile' => null]);
    exit;
}

$profileRepo = new ProfileRepository();
$profile = $profileRepo->getProfile($userId);

echo json_encode(['profile' => $profile]);