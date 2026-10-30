<?php
/**
 * WorkoutValidator (BLL)
 * ------------------------------------------------------------
 * Single source of truth for what a valid workout looks like.
 * Endpoints never trust raw JSON: they pass it through here and
 * receive clean, typed data — or a ValidationException with a
 * message that is safe to show the user.
 *
 * Rule: reject, don't repair. A set with reps = "abc" is an error,
 * not something we silently turn into 0.
 */

class ValidationException extends InvalidArgumentException {}

final class WorkoutValidator
{
    public const MAX_SETS          = 100;    // per workout — blocks oversized payloads
    public const MAX_LABEL_LENGTH  = 100;    // matches workouts.label VARCHAR(100)
    public const MAX_NOTES_LENGTH  = 2000;
    public const MAX_REPS          = 200;
    public const MAX_WEIGHT_KG     = 1000.0; // matches DECIMAL(6,2) headroom
    public const MIN_RPE           = 1.0;
    public const MAX_RPE           = 10.0;
    public const MAX_SET_NUMBER    = 50;

    /**
     * Validates the full sets array. Returns a list of clean sets:
     * ['exercise_id' => int, 'set_number' => int, 'reps' => int,
     *  'weight_kg' => float, 'rpe' => ?float]
     */
    public static function validateSets(mixed $rawSets): array
    {
        if (!is_array($rawSets) || $rawSets === []) {
            throw new ValidationException('Request must include a non-empty "sets" array.');
        }
        if (count($rawSets) > self::MAX_SETS) {
            throw new ValidationException('A workout cannot have more than ' . self::MAX_SETS . ' sets.');
        }

        $clean = [];
        $setNumberByExercise = [];

        foreach (array_values($rawSets) as $i => $set) {
            $pos = $i + 1; // human-friendly position for error messages

            if (!is_array($set)) {
                throw new ValidationException("Set #{$pos} is malformed.");
            }

            $exerciseId = self::positiveInt($set['exercise_id'] ?? null, "Set #{$pos}: exercise_id");
            $reps       = self::intInRange($set['reps'] ?? null, 1, self::MAX_REPS, "Set #{$pos}: reps");
            $weightKg   = self::optionalFloatInRange($set['weight_kg'] ?? null, 0.0, self::MAX_WEIGHT_KG, "Set #{$pos}: weight_kg") ?? 0.0;
            $rpe        = self::optionalFloatInRange($set['rpe'] ?? null, self::MIN_RPE, self::MAX_RPE, "Set #{$pos}: rpe");

            // set_number: trust the client only if it's a sane int, otherwise auto-number per exercise
            $setNumberByExercise[$exerciseId] = ($setNumberByExercise[$exerciseId] ?? 0) + 1;
            $setNumber = isset($set['set_number']) && $set['set_number'] !== ''
                ? self::intInRange($set['set_number'], 1, self::MAX_SET_NUMBER, "Set #{$pos}: set_number")
                : $setNumberByExercise[$exerciseId];

            $clean[] = [
                'exercise_id' => $exerciseId,
                'set_number'  => $setNumber,
                'reps'        => $reps,
                'weight_kg'   => $weightKg,
                'rpe'         => $rpe,
            ];
        }

        return $clean;
    }

        /**
     * Returns a UTC MySQL DATETIME string, or $fallback when nothing was sent.
     * Accepts:
     *   - ISO 8601 with offset: '2026-10-28T11:17:00+01:00', '2026-10-28T10:17:00.000Z' → converted to UTC
     *   - 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s' → already UTC
     */
    public static function validateDate(mixed $raw, string $fallback): string
    {
        if ($raw === null || $raw === '') {
            return $fallback;
        }
        if (!is_string($raw)) {
            throw new ValidationException('workout_date must be a string.');
        }

        
        // ISO 8601 WITH an explicit offset ("...Z", "...+01:00"), e.g. from JS toISOString():
        // unambiguous, so convert it to UTC for storage.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/', $raw, $m)) {
            try {
                $dt = new DateTimeImmutable($raw);
            } catch (Exception $e) {
                throw new ValidationException('workout_date has an invalid format.');
            }
            // Calendar check in the ORIGINAL offset: rejects 2026-02-31 instead of rolling it over
            if ($dt->format('Y-m-d') !== $m[1]) {
                throw new ValidationException('workout_date has an invalid format.');
            }
            $year = (int) $dt->format('Y');
            if ($year < 2000 || $year > 2100) {
                throw new ValidationException('workout_date is out of range.');
            }
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }

        // Formats WITHOUT an offset are taken as UTC (the app's storage timezone)

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
            $dt = DateTime::createFromFormat('!' . $format, $raw);
            // Round-trip check rejects overflow dates like 2026-02-31 that PHP would "roll over"
            if ($dt !== false && $dt->format($format) === $raw) {
                $year = (int) $dt->format('Y');
                if ($year < 2000 || $year > 2100) {
                    throw new ValidationException('workout_date is out of range.');
                }
                return $dt->format('Y-m-d H:i:s');
            }
        }

        throw new ValidationException('workout_date has an invalid format.');
    }

    /** Trims text, enforces max length, turns empty into null. */
    public static function optionalText(mixed $raw, int $maxLength, string $field): ?string
    {
        if ($raw === null) {
            return null;
        }
        if (!is_string($raw)) {
            throw new ValidationException("{$field} must be text.");
        }
        $text = trim($raw);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $maxLength) {
            throw new ValidationException("{$field} cannot exceed {$maxLength} characters.");
        }
        return $text;
    }

    // ---------- primitives (public: RoutineValidator reuses them) ----------

    public static function positiveInt(mixed $raw, string $field): int
    {
        // filter_var rejects "12abc", "1.5", true, arrays — (int) cast would silently accept them
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false || is_bool($raw)) {
            throw new ValidationException("{$field} must be a positive integer.");
        }
        return $value;
    }

    public static function intInRange(mixed $raw, int $min, int $max, string $field): int
    {
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($value === false || is_bool($raw)) {
            throw new ValidationException("{$field} must be an integer between {$min} and {$max}.");
        }
        return $value;
    }

    /** null / '' → null (field is optional). Anything else must be a number in range. */
    public static function optionalFloatInRange(mixed $raw, float $min, float $max, string $field): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $value = filter_var($raw, FILTER_VALIDATE_FLOAT);
        if ($value === false || is_bool($raw) || !is_finite($value) || $value < $min || $value > $max) {
            throw new ValidationException("{$field} must be a number between {$min} and {$max}.");
        }
        return $value;
    }
}