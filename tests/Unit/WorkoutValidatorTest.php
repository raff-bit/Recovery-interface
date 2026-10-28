<?php

use PHPUnit\Framework\TestCase;

/**
 * WorkoutValidator — the gate every workout payload passes before touching the DB.
 * Rule under test: reject, don't repair.
 */
final class WorkoutValidatorTest extends TestCase
{
    private const VALID_SET = ['exercise_id' => 3, 'reps' => 8, 'weight_kg' => 60, 'rpe' => 8];

    /** Asserts that $fn throws ValidationException; $why appears in the failure message. */
    private function assertRejected(callable $fn, string $why): void
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            $this->addToAssertionCount(1);
            return;
        }
        $this->fail("Expected rejection: {$why}");
    }

    private function setWith(array $overrides): array
    {
        return [array_merge(self::VALID_SET, $overrides)];
    }

    // ---------------- sets: accepted ----------------

    public function testNullRpeOnTheLastSetIsAccepted(): void
    {
        // Regression test for the original bug: this used to reject the whole workout
        $clean = WorkoutValidator::validateSets([self::VALID_SET, array_merge(self::VALID_SET, ['rpe' => null])]);

        $this->assertCount(2, $clean);
        $this->assertNull($clean[1]['rpe']);
    }

    public function testNumericStringsBecomeRealNumbers(): void
    {
        $clean = WorkoutValidator::validateSets([['exercise_id' => '3', 'reps' => '10', 'weight_kg' => '', 'rpe' => '']]);

        $this->assertSame(3, $clean[0]['exercise_id']);
        $this->assertSame(10, $clean[0]['reps']);
        $this->assertSame(0.0, $clean[0]['weight_kg']);
        $this->assertNull($clean[0]['rpe']);
    }

    public function testSetNumbersAutoIncrementPerExercise(): void
    {
        $clean = WorkoutValidator::validateSets([self::VALID_SET, self::VALID_SET, ['exercise_id' => 5, 'reps' => 5]]);

        $this->assertSame([1, 2, 1], array_column($clean, 'set_number'));
    }

    // ---------------- sets: rejected ----------------

    public function testMalformedSetPayloadsAreRejected(): void
    {
        $this->assertRejected(fn() => WorkoutValidator::validateSets([]), 'empty sets array');
        $this->assertRejected(fn() => WorkoutValidator::validateSets('not-an-array'), 'sets is not an array');
        $this->assertRejected(fn() => WorkoutValidator::validateSets(['oops']), 'set entry is not an object');
        $this->assertRejected(
            fn() => WorkoutValidator::validateSets(array_fill(0, WorkoutValidator::MAX_SETS + 1, self::VALID_SET)),
            'more than MAX_SETS sets'
        );
    }

    public function testOutOfRangeValuesAreRejected(): void
    {
        $cases = [
            'RPE below 1'          => ['rpe' => 0],
            'RPE above 10'         => ['rpe' => 11],
            'zero reps'            => ['reps' => 0],
            'fractional reps'      => ['reps' => 2.5],
            'negative weight'      => ['weight_kg' => -5],
            'negative exercise_id' => ['exercise_id' => -1],
        ];
        foreach ($cases as $why => $overrides) {
            $this->assertRejected(fn() => WorkoutValidator::validateSets($this->setWith($overrides)), $why);
        }
    }

    public function testValuesThatACastWouldSilentlyAcceptAreRejected(): void
    {
        // (int) "12abc" === 12 and (int) true === 1 — the validator must NOT behave like a cast
        $cases = [
            'garbage reps "12abc"'  => ['reps' => '12abc'],
            'non-numeric weight'    => ['weight_kg' => 'NAN'],
            'boolean exercise_id'   => ['exercise_id' => true],
        ];
        foreach ($cases as $why => $overrides) {
            $this->assertRejected(fn() => WorkoutValidator::validateSets($this->setWith($overrides)), $why);
        }
    }

    // ---------------- dates ----------------

    public function testMissingDateUsesTheFallback(): void
    {
        $this->assertSame('2026-01-01 10:00:00', WorkoutValidator::validateDate(null, '2026-01-01 10:00:00'));
    }

    public function testDatesWithoutOffsetAreTakenAsUtc(): void
    {
        $this->assertSame('2026-10-23 18:30:00', WorkoutValidator::validateDate('2026-10-23T18:30', 'x'));
        $this->assertSame('2026-10-23 18:30:15', WorkoutValidator::validateDate('2026-10-23 18:30:15', 'x'));
    }

    public function testIsoDatesWithAnOffsetAreConvertedToUtc(): void
    {
        $this->assertSame('2026-10-28 10:17:00', WorkoutValidator::validateDate('2026-10-28T11:17:00+01:00', 'x'));
        $this->assertSame('2026-07-15 16:00:00', WorkoutValidator::validateDate('2026-07-15T18:00:00+02:00', 'x'));
        $this->assertSame('2026-10-28 10:17:00', WorkoutValidator::validateDate('2026-10-28T10:17:00.000Z', 'x'));
    }

    public function testInvalidDatesAreRejected(): void
    {
        $this->assertRejected(fn() => WorkoutValidator::validateDate('2026-02-31 10:00:00', 'x'), 'Feb 31 (would roll over to March)');
        $this->assertRejected(fn() => WorkoutValidator::validateDate('2026-02-31T10:00:00Z', 'x'), 'Feb 31 in ISO form');
        $this->assertRejected(fn() => WorkoutValidator::validateDate('tomorrow', 'x'), 'free-text date');
        $this->assertRejected(fn() => WorkoutValidator::validateDate('1970-01-01 00:00:00', 'x'), 'year out of range');
    }

    // ---------------- text ----------------

    public function testTextIsTrimmedAndBlankBecomesNull(): void
    {
        $this->assertSame('Push Day', WorkoutValidator::optionalText('  Push Day ', 100, 'label'));
        $this->assertNull(WorkoutValidator::optionalText('   ', 100, 'label'));
    }

    public function testLengthIsCountedInCharactersNotBytes(): void
    {
        // "é" is 2 bytes in UTF-8; 100 of them must still fit a 100-character limit
        $this->assertNotNull(WorkoutValidator::optionalText(str_repeat('é', 100), 100, 'label'));
    }

    public function testInvalidTextIsRejected(): void
    {
        $this->assertRejected(fn() => WorkoutValidator::optionalText(str_repeat('a', 101), 100, 'label'), 'over max length');
        $this->assertRejected(fn() => WorkoutValidator::optionalText(['array'], 100, 'label'), 'not a string');
    }
}