<?php
// Bootstrap first: hardened session, error handling, headers, CSRF
require_once __DIR__ . '/bootstrap.php';

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
        jsonResponse(['error' => 'Authentication required. Please sign in.'], 401);
    }
    return $userId;
}

function isAuthenticated(): bool
{
    return isset($_SESSION['user_id']);
}

function setAuthenticatedUser(int $userId): void
{
    // New session ID on privilege change → a pre-planted session ID becomes useless
    session_regenerate_id(true);
    // New CSRF token too: a token seen while logged OUT is worthless once logged IN.
    // csrfToken() mints a fresh one; login/register return it and api.js picks it up.
    unset($_SESSION['csrf_token']);
    $_SESSION['user_id'] = $userId;
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }
    session_destroy();
}