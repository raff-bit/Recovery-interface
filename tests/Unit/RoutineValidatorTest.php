<?php

use PHPUnit\Framework\TestCase;

// Explicit require: works even before "composer dump-autoload" picks up the new class
require_once __DIR__ . '/../../bll/RoutineValidator.php';

/**
 * RoutineValidator — the gate every routine payload passes before touching the DB.
 */
final class RoutineValidatorTest extends TestCase
{
    private const VALID_ITEM = ['exercise_id' => 3, 'sets' => 4, 'reps' => 8, 'weight_kg' => 60, 'rpe' => 8];

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

    private function itemWith(array $overrides): array
    {
        return [array_merge(self::VALID_ITEM, $overrides)];
    }

    // ---------------- name ----------------

    public function testNameIsTrimmed(): void
    {
        $this->assertSame('Push day', RoutineValidator::validateName('  Push day  '));
    }

    public function testMissingOrBlankNameIsRejected(): void
    {
        $this->assertRejected(fn() => RoutineValidator::validateName(null), 'null name');
        $this->assertRejected(fn() => RoutineValidator::validateName('   '), 'blank name');
        $this->assertRejected(fn() => RoutineValidator::validateName(['Push']), 'array as name');
    }

    public function testNameLongerThan100CharactersIsRejected(): void
    {
        $this->assertSame(100, mb_strlen(RoutineValidator::validateName(str_repeat('é', 100))));
        $this->assertRejected(fn() => RoutineValidator::validateName(str_repeat('a', 101)), '101 chars');
    }

    // ---------------- items: accepted ----------------

    public function testValidItemsComeBackTyped(): void
    {
        $clean = RoutineValidator::validateItems([
            ['exercise_id' => '3', 'sets' => '4', 'reps' => '8', 'weight_kg' => '62.5', 'rpe' => '8.5'],
        ]);

        $this->assertSame(
            [['exercise_id' => 3, 'sets' => 4, 'reps' => 8, 'weight_kg' => 62.5, 'rpe' => 8.5]],
            $clean
        );
    }

    public function testWeightAndRpeAreOptional(): void
    {
        $clean = RoutineValidator::validateItems($this->itemWith(['weight_kg' => null, 'rpe' => '']));

        $this->assertNull($clean[0]['weight_kg']);
        $this->assertNull($clean[0]['rpe']);
    }

    public function testThirtyItemsAreAccepted(): void
    {
        $this->assertCount(30, RoutineValidator::validateItems(array_fill(0, 30, self::VALID_ITEM)));
    }

    // ---------------- items: rejected ----------------

    public function testEmptyOrNonArrayItemsAreRejected(): void
    {
        $this->assertRejected(fn() => RoutineValidator::validateItems([]), 'empty list');
        $this->assertRejected(fn() => RoutineValidator::validateItems('items'), 'string');
        $this->assertRejected(fn() => RoutineValidator::validateItems(null), 'null');
        $this->assertRejected(fn() => RoutineValidator::validateItems(['not-an-object']), 'item is a string');
    }

    public function testMoreThanThirtyItemsAreRejected(): void
    {
        $this->assertRejected(fn() => RoutineValidator::validateItems(array_fill(0, 31, self::VALID_ITEM)), '31 items');
    }

    public function testSetsOutsideOneToTwentyAreRejected(): void
    {
        $this->assertRejected(fn() => RoutineValidator::validateItems($this->itemWith(['sets' => 0])), 'sets 0');
        $this->assertRejected(fn() => RoutineValidator::validateItems($this->itemWith(['sets' => 21])), 'sets 21');
        $this->assertRejected(fn() => RoutineValidator::validateItems($this->itemWith(['sets' => 2.5])), 'sets 2.5');
    }

    public function testBadTypesAndRangesAreRejectedNotRepaired(): void
    {
        $cases = [
            'exercise_id 0'      => ['exercise_id' => 0],
            'exercise_id "3abc"' => ['exercise_id' => '3abc'],
            'exercise_id true'   => ['exercise_id' => true],
            'reps 0'             => ['reps' => 0],
            'reps 201'           => ['reps' => 201],
            'missing reps'       => ['reps' => null],
            'weight -1'          => ['weight_kg' => -1],
            'weight 1001'        => ['weight_kg' => 1001],
            'weight "heavy"'     => ['weight_kg' => 'heavy'],
            'rpe 11'             => ['rpe' => 11],
            'rpe 0.5'            => ['rpe' => 0.5],
            'rpe true'           => ['rpe' => true],
        ];
        foreach ($cases as $why => $override) {
            $this->assertRejected(fn() => RoutineValidator::validateItems($this->itemWith($override)), $why);
        }
    }
}
