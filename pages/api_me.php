<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../dal/UserRepository.php';

$userId = getCurrentUserId();
$user = null;

// Só pesquisa na base de dados se houver um ID válido
if ($userId !== null) {
    $userRepo = new UserRepository();
    $user = $userRepo->findById($userId);

    // Account deleted while the session was alive → treat as logged out
    if ($user === null) {
        logoutUser();
    }
}

jsonResponse([
    'authenticated' => $user !== null,
    'user'          => $user,
    // The frontend reads this once and sends it back as X-CSRF-Token on every POST
    'csrf_token'    => csrfToken(),
]);