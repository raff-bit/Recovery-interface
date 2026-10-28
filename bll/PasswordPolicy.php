<?php
/**
 * PasswordPolicy (BLL)
 * ------------------------------------------------------------
 * Length beats complexity rules: a 12-char passphrase is far
 * stronger than "P@ss1" and easier to remember (NIST SP 800-63B).
 *
 * MAX is in BYTES, not characters: bcrypt (PHP's PASSWORD_DEFAULT)
 * silently ignores everything after byte 72, so two long passwords
 * sharing their first 72 bytes would both work. We refuse instead.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 10;
    public const MAX_BYTES  = 72;

    /** Small deny-list of the most common passwords that pass the length rule. */
    private const COMMON = [
        '1234567890', '0123456789', 'qwertyuiop', 'password12', 'password123',
        'iloveyou12', 'qwerty1234', '1q2w3e4r5t', 'aaaaaaaaaa', 'abcdefghij',
    ];

    /** Returns null if acceptable, or a user-facing reason if not. */
    public static function check(string $password, string $email = '', string $name = ''): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'Password must be at least ' . self::MIN_LENGTH . ' characters long.';
        }
        if (strlen($password) > self::MAX_BYTES) {
            return 'Password is too long (max ' . self::MAX_BYTES . ' bytes).';
        }

        $lower = mb_strtolower($password);
        if (in_array($lower, self::COMMON, true)) {
            return 'This password is too common. Please choose another.';
        }

                $emailLocal = mb_strtolower(explode('@', $email)[0]);
        if ($emailLocal !== '' && $lower === $emailLocal) {
            return 'Password cannot be the same as your email.';
        }
        if ($name !== '' && $lower === mb_strtolower(trim($name))) {
            return 'Password cannot be the same as your name.';
        }

        return null;
    }
}