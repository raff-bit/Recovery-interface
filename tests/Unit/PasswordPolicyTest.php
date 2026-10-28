<?php

use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testAGoodPassphraseIsAccepted(): void
    {
        $this->assertNull(PasswordPolicy::check('correct-horse-battery', 'rafael@example.com', 'Rafael'));
    }

    public function testExactlyTheMinimumLengthIsAccepted(): void
    {
        $this->assertNull(PasswordPolicy::check(str_repeat('x', PasswordPolicy::MIN_LENGTH - 1) . 'y'));
    }

    public function testTooShortIsRejected(): void
    {
        $this->assertNotNull(PasswordPolicy::check(str_repeat('x', PasswordPolicy::MIN_LENGTH - 1)));
    }

    public function testMoreThan72BytesIsRejectedBecauseBcryptWouldTruncateIt(): void
    {
        $this->assertNull(PasswordPolicy::check(str_repeat('a', 72)));
        $this->assertNotNull(PasswordPolicy::check(str_repeat('a', 73)));
        // 37 × "é" = 37 characters but 74 BYTES → must be rejected
        $this->assertNotNull(PasswordPolicy::check(str_repeat('é', 37)));
    }

    public function testCommonPasswordsAreRejectedRegardlessOfCase(): void
    {
        $this->assertNotNull(PasswordPolicy::check('1234567890'));
        $this->assertNotNull(PasswordPolicy::check('QwErTyUiOp'));
    }

    public function testPasswordEqualToEmailOrNameIsRejected(): void
    {
        $this->assertNotNull(PasswordPolicy::check('rafaelmonteiro', 'RafaelMonteiro@example.com'));
        $this->assertNotNull(PasswordPolicy::check('rafael monteiro', 'x@example.com', 'Rafael Monteiro'));
    }
}