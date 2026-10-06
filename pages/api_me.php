<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/UserRepository.php';

$userId = getCurrentUserId();
$user = null;

// Só pesquisa na base de dados se houver um ID válido
if ($userId !== null) {
    $userRepo = new UserRepository();
    $user = $userRepo->findById($userId);
}

echo json_encode([
    'authenticated' => isAuthenticated(),
    'user'          => $user,
]);