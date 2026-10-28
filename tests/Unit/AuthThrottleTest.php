<?php

use PHPUnit\Framework\TestCase;

/**
 * AuthThrottle — brute-force protection rules, tested without a database:
 * the repository is a stub that reports whatever attempt counts we choose.
 */
final class AuthThrottleTest extends TestCase
{
    private function throttle(int $failsForAccount = 0, int $failsFromIp = 0, int $registrationsFromIp = 0): AuthThrottle
    {
        $repo = $this->createStub(AuthAttemptRepository::class);
        $repo->method('countByEmail')->willReturn($failsForAccount);
        $repo->method('countByIp')->willReturnCallback(
            fn(string $action) => $action === 'register' ? $registrationsFromIp : $failsFromIp
        );
        return new AuthThrottle($repo);
    }

    public function testLoginIsAllowedBelowBothLimits(): void
    {
        $throttle = $this->throttle(AuthThrottle::MAX_FAILS_PER_ACCOUNT - 1, AuthThrottle::MAX_FAILS_PER_IP - 1);

        $this->assertSame(0, $throttle->loginBlockedFor('1.2.3.4', 'a@b.com'));
    }

    public function testAccountIsBlockedAfterTooManyFailures(): void
    {
        $throttle = $this->throttle(failsForAccount: AuthThrottle::MAX_FAILS_PER_ACCOUNT);

        $this->assertSame(AuthThrottle::LOGIN_WINDOW_MINUTES * 60, $throttle->loginBlockedFor('1.2.3.4', 'a@b.com'));
    }

    public function testIpIsBlockedAfterTooManyFailuresAcrossAccounts(): void
    {
        $throttle = $this->throttle(failsFromIp: AuthThrottle::MAX_FAILS_PER_IP);

        $this->assertGreaterThan(0, $throttle->loginBlockedFor('1.2.3.4', 'fresh@b.com'));
    }

    public function testRegistrationIsBlockedAfterTheHourlyLimit(): void
    {
        $this->assertSame(0, $this->throttle(registrationsFromIp: AuthThrottle::MAX_REGISTERS_PER_IP - 1)->registerBlockedFor('1.2.3.4'));
        $this->assertGreaterThan(0, $this->throttle(registrationsFromIp: AuthThrottle::MAX_REGISTERS_PER_IP)->registerBlockedFor('1.2.3.4'));
    }

    public function testEmailHashIsCaseAndWhitespaceInsensitive(): void
    {
        // Otherwise "A@b.com" and " a@b.com" would each get their own 5 attempts
        $this->assertSame(AuthThrottle::emailHash('a@b.com'), AuthThrottle::emailHash('  A@B.com '));
    }

    public function testEmailHashNeverContainsTheEmailItself(): void
    {
        $hash = AuthThrottle::emailHash('rafael@example.com');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
        $this->assertStringNotContainsString('rafael', $hash);
    }
}