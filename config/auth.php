<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Devolve o ID do utilizador autenticado.
 * Enquanto o frontend de login não estiver ativo, faz fallback para o ID 1
 * para manter os dados atuais funcionais.
 */
function getCurrentUserId(): int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 1;
}

function isAuthenticated(): bool
{
    return isset($_SESSION['user_id']);
}

function setAuthenticatedUser(int $userId): void
{
    $_SESSION['user_id'] = $userId;
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}