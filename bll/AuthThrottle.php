<?php
require_once __DIR__ . '/../dal/AuthAttemptRepository.php';

/**
 * AuthThrottle (BLL) — brute-force and sign-up spam protection.
 * ------------------------------------------------------------
 * Two independent limits for logins:
 *   - per ACCOUNT: stops someone guessing one person's password
 *     from many IPs
 *   - per IP: stops one machine trying many accounts
 * Only FAILED logins count, so a real user is never locked out
 * by their own successful sign-ins.
 */
final class AuthThrottle
{
    public const LOGIN_WINDOW_MINUTES   = 15;
    public const MAX_FAILS_PER_ACCOUNT  = 5;
    public const MAX_FAILS_PER_IP       = 20;

    public const REGISTER_WINDOW_MINUTES = 60;
    public const MAX_REGISTERS_PER_IP    = 5;

    private const PURGE_AFTER_HOURS = 24;

    public function __construct(private AuthAttemptRepository $repo) {}

    /** Hash so the table never stores raw emails. Normalized so "A@x.com" == "a@x.com". */
    public static function emailHash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /** Returns seconds to wait if blocked, or 0 if the login may proceed. */
    public function loginBlockedFor(string $ip, string $email): int
    {
        $byAccount = $this->repo->countByEmail('login_failed', self::emailHash($email), self::LOGIN_WINDOW_MINUTES);
        $byIp      = $this->repo->countByIp('login_failed', $ip, self::LOGIN_WINDOW_MINUTES);

        if ($byAccount >= self::MAX_FAILS_PER_ACCOUNT || $byIp >= self::MAX_FAILS_PER_IP) {
            return self::LOGIN_WINDOW_MINUTES * 60;
        }
        return 0;
    }

    public function recordLoginFailure(string $ip, string $email): void
    {
        $this->repo->record('login_failed', $ip, self::emailHash($email));
        // ~1 in 50 requests tidies up old rows — no cron job needed
        if (random_int(1, 50) === 1) {
            $this->repo->purgeOlderThan(self::PURGE_AFTER_HOURS);
        }
    }

    public function recordLoginSuccess(string $email): void
    {
        $this->repo->clearForEmail('login_failed', self::emailHash($email));
    }

    public function registerBlockedFor(string $ip): int
    {
        $count = $this->repo->countByIp('register', $ip, self::REGISTER_WINDOW_MINUTES);
        return $count >= self::MAX_REGISTERS_PER_IP ? self::REGISTER_WINDOW_MINUTES * 60 : 0;
    }

    public function recordRegistration(string $ip): void
    {
        $this->repo->record('register', $ip, null);
    }
}