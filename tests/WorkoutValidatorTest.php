<?php
/**
 * Test Suite — WorkoutValidator (input validation layer)
 * Run from terminal: php tests/WorkoutValidatorTest.php
 */

require_once __DIR__ . '/../bll/WorkoutValidator.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $name): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  \033[32m✔ PASS\033[0m: {$name}\n";
        $passed++;
    } else {
        echo "  \033[31m✖ FAIL\033[0m: {$name}\n";
        $failed++;
    }
}

/** Returns true if $fn throws ValidationException. */
function rejects(callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (ValidationException $e) {
        return true;
    }
}

$validSet = ['exercise_id' => 3, 'reps' => 8, 'weight_kg' => 60, 'rpe' => 8];

echo "\nWorkoutValidator — sets\n";

// Regression: the original bug — empty RPE on the LAST set rejected the whole workout
$clean = WorkoutValidator::validateSets([$validSet, ['exercise_id' => 3, 'reps' => 8, 'weight_kg' => 60, 'rpe' => null]]);
check(count($clean) === 2 && $clean[1]['rpe'] === null, 'Regression: null RPE on last set is accepted');

$clean = WorkoutValidator::validateSets([['exercise_id' => '3', 'reps' => '10', 'weight_kg' => '', 'rpe' => '']]);
check($clean[0]['exercise_id'] === 3 && $clean[0]['reps'] === 10, 'Numeric strings are converted to proper ints');
check($clean[0]['weight_kg'] === 0.0 && $clean[0]['rpe'] === null, 'Empty weight → 0.0, empty RPE → null');

$clean = WorkoutValidator::validateSets([$validSet, $validSet, ['exercise_id' => 5, 'reps' => 5]]);
check($clean[0]['set_number'] === 1 && $clean[1]['set_number'] === 2 && $clean[2]['set_number'] === 1, 'Set numbers auto-increment per exercise');

check(rejects(fn() => WorkoutValidator::validateSets([])), 'Empty sets array is rejected');
check(rejects(fn() => WorkoutValidator::validateSets('not-an-array')), 'Non-array sets is rejected');
check(rejects(fn() => WorkoutValidator::validateSets(array_fill(0, WorkoutValidator::MAX_SETS + 1, $validSet))), 'More than MAX_SETS is rejected');
check(rejects(fn() => WorkoutValidator::validateSets([[...$validSet, 'rpe' => 0]])), 'RPE below 1 is rejected');
check(rejects(fn() => WorkoutValidator::validateSets([[...$validSet, 'rpe' => 11]])), 'RPE above 10 is rejected');
check(rejects(fn() => WorkoutValidator::validateSets([[...$validSet, 'reps' => 0]])), 'Zero reps is rejected');
check(rejects(fn() => WorkoutValidator::validateSets([[...$validSet, 'reps' => '12abc']])), 'Garbage reps "12abc" is rejected (not silently cast to 12)');
check(rejects(fn() => WorkoutValidator::validateSets([[...$validSet, 'reps' => 2.5]])), 'Fractional reps are rejected');
check(rejects(fn() => WorkoutValidator::validateSets([[...$validSet, 'weight_kg' => -5]])), 'Negative weight is rejected');
check(rejects(fn() => WorkoutValidator::validateSets([[...$validSet, 'weight_kg' => 'NAN']])), 'Non-numeric weight is rejected');
check(rejects(fn() => WorkoutValidator::validateSets([[...$validSet, 'exercise_id' => -1]])), 'Negative exercise_id is rejected');
check(rejects(fn() => WorkoutValidator::validateSets([[...$validSet, 'exercise_id' => true]])), 'Boolean exercise_id is rejected');
check(rejects(fn() => WorkoutValidator::validateSets(['oops'])), 'Non-object set entry is rejected');

echo "\nWorkoutValidator — dates & text\n";

check(WorkoutValidator::validateDate(null, '2026-01-01 10:00:00') === '2026-01-01 10:00:00', 'Missing date uses fallback');
check(WorkoutValidator::validateDate('2026-10-23T18:30', 'x') === '2026-10-23 18:30:00', 'datetime-local format is normalized');
check(WorkoutValidator::validateDate('2026-10-23 18:30:15', 'x') === '2026-10-23 18:30:15', 'MySQL DATETIME format is accepted');
check(rejects(fn() => WorkoutValidator::validateDate('2026-02-31 10:00:00', 'x')), 'Impossible date (Feb 31) is rejected');
check(rejects(fn() => WorkoutValidator::validateDate('tomorrow', 'x')), 'Free-text date is rejected');
check(rejects(fn() => WorkoutValidator::validateDate('1970-01-01 00:00:00', 'x')), 'Out-of-range year is rejected');
check(WorkoutValidator::validateDate('2026-10-28T11:17:00+01:00', 'x') === '2026-10-28 10:17:00', 'ISO with +01:00 offset is converted to UTC');
check(WorkoutValidator::validateDate('2026-07-15T18:00:00+02:00', 'x') === '2026-07-15 16:00:00', 'ISO with summer +02:00 offset is converted to UTC');
check(WorkoutValidator::validateDate('2026-10-28T10:17:00.000Z', 'x') === '2026-10-28 10:17:00', 'JS toISOString() format (…Z) is accepted');
check(rejects(fn() => WorkoutValidator::validateDate('2026-02-31T10:00:00Z', 'x')), 'Impossible ISO date (Feb 31) is rejected');

check(WorkoutValidator::optionalText('   ', 100, 'label') === null, 'Whitespace-only text becomes null');
check(WorkoutValidator::optionalText('  Push Day ', 100, 'label') === 'Push Day', 'Text is trimmed');
check(WorkoutValidator::optionalText(str_repeat('é', 100), 100, 'label') !== null, 'Length counts characters, not bytes (UTF-8 safe)');
check(rejects(fn() => WorkoutValidator::optionalText(str_repeat('a', 101), 100, 'label')), 'Over-length text is rejected');
check(rejects(fn() => WorkoutValidator::optionalText(['array'], 100, 'label')), 'Non-string text is rejected');

echo "\n" . str_repeat('=', 50) . "\n";
echo "Results: {$passed} passed, {$failed} failed.\n";
echo str_repeat('=', 50) . "\n";

exit($failed > 0 ? 1 : 0);