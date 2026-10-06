<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Devolve o ID do utilizador autenticado ou null se não houver sessão ativa.
 */
function getCurrentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Valida a existência de sessão ativa.
 * Interrompe a execução com HTTP 401 se o utilizador for anónimo.
 */
function requireAuth(): int
{
    $userId = getCurrentUserId();
    if ($userId === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required. Please sign in.']);
        exit;
    }
    return $userId;
}

function isAuthenticated(): bool
{
    return isset($_SESSION['user_id']);
}

function setAuthenticatedUser(int $userId): void
{
    session_regenerate_id(true);
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