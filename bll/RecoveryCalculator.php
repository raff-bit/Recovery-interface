<?php
require_once __DIR__ . '/../dal/MuscleRepository.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';
require_once __DIR__ . '/../dal/ExerciseRepository.php';
require_once __DIR__ . '/../dal/ProfileRepository.php';

/**
 * RecoveryCalculator
 * ------------------------------------------------------------
 * Heuristic muscle-readiness model. See prior versions' notes for
 * the base formula. This version adds optional personalization:
 *
 * WITHOUT a saved profile: behaves EXACTLY as before. This matters —
 * existing readiness numbers must not shift for anyone who hasn't
 * set one up.
 *
 * WITH a profile:
 *   - Each set's fatigue is scaled by RELATIVE LOAD (weight lifted
 *     relative to body weight), instead of ignoring load entirely.
 *     Bodyweight exercises use (body_weight + any added weight) as
 *     the effective load, so an unweighted pull-up still counts as
 *     roughly "1x bodyweight" of load rather than zero.
 *   - Each muscle's recovery_hours is scaled by an activity-level
 *     multiplier (fitter/more active recovers faster) and a modest
 *     sex-based multiplier (a soft, population-average adjustment,
 *     not a precise personal measurement).
 *   - Height is intentionally NOT used in the math — it doesn't map
 *     cleanly to fatigue or recovery, so it stays purely informational.
 */
class RecoveryCalculator
{
    private const BASE_FATIGUE_PER_SET = 12.0;
    private const DECAY_CONSTANT       = 3.0;
    private const LOOKBACK_DAYS        = 14;
    private const DEFAULT_RPE          = 7.0;

    // Relative load is clamped so an extreme entry can't blow up the model.
    private const MIN_RELATIVE_LOAD = 0.3;
    private const MAX_RELATIVE_LOAD = 3.5;

    private const ACTIVITY_RECOVERY_MULTIPLIERS = [
        'sedentary'         => 1.15, // recovers slower, needs more time
        'lightly_active'    => 1.05,
        'moderately_active' => 1.00, // baseline — same as no profile
        'very_active'       => 0.90,
        'athlete'           => 0.80, // recovers faster
    ];

    private const SEX_RECOVERY_MULTIPLIERS = [
        'female'      => 0.95, // modest, population-average adjustment
        'male'        => 1.00,
        'unspecified' => 1.00,
    ];

    private MuscleRepository $muscleRepo;
    private WorkoutRepository $workoutRepo;
    private ExerciseRepository $exerciseRepo;
    private ProfileRepository $profileRepo;

    public function __construct(
        MuscleRepository $muscleRepo,
        WorkoutRepository $workoutRepo,
        ExerciseRepository $exerciseRepo,
        ProfileRepository $profileRepo
    ) {
        $this->muscleRepo = $muscleRepo;
        $this->workoutRepo = $workoutRepo;
        $this->exerciseRepo = $exerciseRepo;
        $this->profileRepo = $profileRepo;
    }

    public function getAllMuscleReadiness(int $userId): array
    {
        $profile = $this->profileRepo->getProfile($userId);
        $muscles = $this->muscleRepo->getAll();
        $sets = $this->workoutRepo->getRecentSetsWithActivation($userId, self::LOOKBACK_DAYS);

        $byMuscle = [];
        foreach ($sets as $row) {
            $byMuscle[$row['muscle_id']][] = $row;
        }

        $result = [];
        foreach ($muscles as $m) {
            $contributions = $byMuscle[$m['id']] ?? [];
            $recoveryHours = $this->effectiveRecoveryHours((int) $m['recovery_hours'], $profile);
            [$fatigue, $hoursSinceLast] = $this->computeFatigue($contributions, $recoveryHours, null, $profile);
            $readiness = (int) round(max(0, min(100, 100 - $fatigue)));
            [$status, $color] = $this->statusFor($readiness);

            $result[] = [
                'id'                      => (int) $m['id'],
                'muscle_key'              => $m['muscle_key'],
                'display_name'            => $m['display_name'],
                'body_region'             => $m['body_region'],
                'view_side'               => $m['view_side'],
                'recovery_hours'          => $recoveryHours,
                'readiness'               => $readiness,
                'fatigue'                 => round($fatigue, 1),
                'status'                  => $status,
                'status_color'            => $color,
                'hours_since_last_worked' => $hoursSinceLast,
                'personalized'            => $profile !== null,
            ];
        }

        return $result;
    }

    private function ageRecoveryMultiplier(?int $age): float
    {
        if ($age === null || $age <= 0) {
            return 1.0;
        }
        if ($age < 30) {
            return 1.00; // Baseline ótima de regeneração
        }
        if ($age < 40) {
            return 1.05; // +5% de tempo necessário
        }
        if ($age < 50) {
            return 1.12; // +12% de tempo necessário
        }
        return 1.22; // +22% de tempo necessário (50+)
    }

    /**
     * Scales recovery_hours by activity level, sex and age.
     */
    private function effectiveRecoveryHours(int $baseHours, ?array $profile): int
    {
        if ($profile === null) {
            return $baseHours;
        }

        $activityMult = self::ACTIVITY_RECOVERY_MULTIPLIERS[$profile['activity_level']] ?? 1.0;
        $sexMult = self::SEX_RECOVERY_MULTIPLIERS[$profile['sex']] ?? 1.0;
        $age = isset($profile['age']) && $profile['age'] !== null ? (int) $profile['age'] : null;
        $ageMult = $this->ageRecoveryMultiplier($age);

        return (int) round($baseHours * $activityMult * $sexMult * $ageMult);
    }
    /**
     * How heavy was this set relative to the person's own body weight?
     * 1.0 means "roughly your body weight" — no scaling up or down.
     * Returns 1.0 (no-op) whenever there's no profile, which is what
     * preserves the original, unpersonalized formula exactly.
     */
    private function relativeLoadFactor(array $setRow, ?array $profile): float
    {
        if ($profile === null || empty($profile['body_weight_kg']) || (float) $profile['body_weight_kg'] <= 0) {
            return 1.0;
        }

        $bodyWeight = (float) $profile['body_weight_kg'];
        $weightKg = (float) ($setRow['weight_kg'] ?? 0);
        $isBodyweight = !empty($setRow['is_bodyweight']);

        // A bodyweight exercise's "load" is your own body weight, plus
        // whatever's added (a weighted pull-up, a weighted dip, etc.).
        $effectiveLoad = $isBodyweight ? ($bodyWeight + $weightKg) : $weightKg;

        if ($effectiveLoad <= 0) {
            return self::MIN_RELATIVE_LOAD;
        }

        $factor = $effectiveLoad / $bodyWeight;
        return max(self::MIN_RELATIVE_LOAD, min(self::MAX_RELATIVE_LOAD, $factor));
    }

    private function computeFatigue(array $contributions, int $recoveryHours, ?DateTime $referenceDate = null, ?array $profile = null): array
    {
        $total = 0.0;
        $hoursSinceLast = null;
        $now = $referenceDate ?? new DateTime();

        foreach ($contributions as $row) {
            $workoutDate = new DateTime($row['workout_date']);
            $hoursElapsed = ($now->getTimestamp() - $workoutDate->getTimestamp()) / 3600;
            if ($hoursElapsed < 0) {
                $hoursElapsed = 0;
            }

            if ($hoursSinceLast === null || $hoursElapsed < $hoursSinceLast) {
                $hoursSinceLast = $hoursElapsed;
            }

            $rpe = $row['rpe'] !== null ? (float) $row['rpe'] : self::DEFAULT_RPE;
            $activation = (float) $row['activation_weight'];
            $loadFactor = $this->relativeLoadFactor($row, $profile);

            $setFatigue = self::BASE_FATIGUE_PER_SET * ($rpe / 10) * $activation * $loadFactor;
            $remainingFraction = exp(-self::DECAY_CONSTANT * $hoursElapsed / max(1, $recoveryHours));

            $total += $setFatigue * $remainingFraction;
        }

        return [$total, $hoursSinceLast];
    }

    /**
     * Projects readiness forward to a future (or any arbitrary) date, and
     * shows the impact of a hypothetical workout on that date. Recovery
     * speed is personalized when a profile exists; relative-load scaling
     * is NOT applied here, since the planner doesn't currently collect a
     * planned weight for hypothetical sets.
     */
    public function previewReadiness(array $hypotheticalSets, DateTime $referenceDate, int $userId): array
    {
        $profile = $this->profileRepo->getProfile($userId);
        $muscles = $this->muscleRepo->getAll();

        $realSets = $this->workoutRepo->getRecentSetsWithActivation($userId, self::LOOKBACK_DAYS);
        $byMuscleReal = [];
        foreach ($realSets as $row) {
            $byMuscleReal[$row['muscle_id']][] = $row;
        }

        $exerciseIds = array_values(array_unique(array_map(
            fn($set) => (int) $set['exercise_id'],
            $hypotheticalSets
        )));
        $activations = $this->exerciseRepo->getMuscleActivationsForExercises($exerciseIds);

        $activationsByExercise = [];
        foreach ($activations as $row) {
            $activationsByExercise[$row['exercise_id']][] = $row;
        }

        $byMuscleHypothetical = [];
        foreach ($hypotheticalSets as $set) {
            $exerciseId = (int) $set['exercise_id'];
            $rpe = isset($set['rpe']) && $set['rpe'] !== null ? (float) $set['rpe'] : null;
            $exerciseActivations = $activationsByExercise[$exerciseId] ?? [];

            foreach ($exerciseActivations as $act) {
                $byMuscleHypothetical[$act['muscle_id']][] = [
                    'activation_weight' => $act['activation_weight'],
                    'rpe'               => $rpe,
                    'workout_date'      => $referenceDate->format('Y-m-d H:i:s'),
                ];
            }
        }

        $result = [];
        foreach ($muscles as $m) {
            $recoveryHours = $this->effectiveRecoveryHours((int) $m['recovery_hours'], $profile);
            $realContribs = $byMuscleReal[$m['id']] ?? [];

            [$beforeFatigue, ] = $this->computeFatigue($realContribs, $recoveryHours, $referenceDate, $profile);
            $beforeReadiness = (int) round(max(0, min(100, 100 - $beforeFatigue)));
            [$statusBefore, $colorBefore] = $this->statusFor($beforeReadiness);

            $hypoContribs = $byMuscleHypothetical[$m['id']] ?? [];
            $combined = array_merge($realContribs, $hypoContribs);
            [$afterFatigue, ] = $this->computeFatigue($combined, $recoveryHours, $referenceDate, $profile);
            $afterReadiness = (int) round(max(0, min(100, 100 - $afterFatigue)));
            [$statusAfter, $colorAfter] = $this->statusFor($afterReadiness);

            $result[] = [
                'muscle_key'          => $m['muscle_key'],
                'display_name'        => $m['display_name'],
                'body_region'         => $m['body_region'],
                'view_side'           => $m['view_side'],
                'readiness_before'    => $beforeReadiness,
                'status_before'       => $statusBefore,
                'status_color_before' => $colorBefore,
                'readiness_after'     => $afterReadiness,
                'status_after'        => $statusAfter,
                'status_color_after'  => $colorAfter,
                'affected'            => !empty($hypoContribs),
            ];
        }

        return $result;
    }

    private function statusFor(int $readiness): array
    {
        if ($readiness >= 85) {
            return ['Fully Recovered', '#39FF88'];
        }
        if ($readiness >= 60) {
            return ['Ready', '#7CE8FF'];
        }
        if ($readiness >= 35) {
            return ['Recovering', '#FFB347'];
        }
        return ['Fatigued — Rest Advised', '#FF5C5C'];
    }
}