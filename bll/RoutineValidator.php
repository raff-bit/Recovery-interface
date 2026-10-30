<?php
require_once __DIR__ . '/WorkoutValidator.php';

/**
 * RoutineValidator (BLL) — what a valid routine looks like.
 * ------------------------------------------------------------
 * Reuses WorkoutValidator's strict primitives (reject, don't repair).
 * The limits keep one account from filling the database: a routine is
 * a plan for one session, not an archive.
 */
final class RoutineValidator
{
    public const MAX_ROUTINES_PER_USER = 30;
    public const MAX_ITEMS             = 30;
    public const MAX_NAME_LENGTH       = 100;
    public const MAX_SETS_PER_ITEM     = 20;

    /** Returns the trimmed routine name, or throws. */
    public static function validateName(mixed $raw): string
    {
        $name = WorkoutValidator::optionalText($raw, self::MAX_NAME_LENGTH, 'Routine name');
        if ($name === null) {
            throw new ValidationException('Give the routine a name.');
        }
        return $name;
    }

    /**
     * Returns clean items: ['exercise_id' => int, 'sets' => int, 'reps' => int,
     * 'weight_kg' => ?float, 'rpe' => ?float]
     */
    public static function validateItems(mixed $rawItems): array
    {
        if (!is_array($rawItems) || $rawItems === []) {
            throw new ValidationException('A routine needs at least one exercise.');
        }
        if (count($rawItems) > self::MAX_ITEMS) {
            throw new ValidationException('A routine can have at most ' . self::MAX_ITEMS . ' exercises.');
        }

        $clean = [];
        foreach (array_values($rawItems) as $i => $item) {
            $pos = $i + 1;
            if (!is_array($item)) {
                throw new ValidationException("Exercise #{$pos} is malformed.");
            }
            $clean[] = [
                'exercise_id' => WorkoutValidator::positiveInt($item['exercise_id'] ?? null, "Exercise #{$pos}: exercise_id"),
                'sets'        => WorkoutValidator::intInRange($item['sets'] ?? null, 1, self::MAX_SETS_PER_ITEM, "Exercise #{$pos}: sets"),
                'reps'        => WorkoutValidator::intInRange($item['reps'] ?? null, 1, WorkoutValidator::MAX_REPS, "Exercise #{$pos}: reps"),
                'weight_kg'   => WorkoutValidator::optionalFloatInRange($item['weight_kg'] ?? null, 0.0, WorkoutValidator::MAX_WEIGHT_KG, "Exercise #{$pos}: weight_kg"),
                'rpe'         => WorkoutValidator::optionalFloatInRange($item['rpe'] ?? null, WorkoutValidator::MIN_RPE, WorkoutValidator::MAX_RPE, "Exercise #{$pos}: rpe"),
            ];
        }
        return $clean;
    }
}
