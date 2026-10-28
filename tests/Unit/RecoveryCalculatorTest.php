<?php

use PHPUnit\Framework\TestCase;

/**
 * RecoveryCalculator — the fatigue/recovery model (see docs/recovery-model.md).
 *
 * Repositories are replaced with STUBS: objects that return canned data instead
 * of querying MySQL. That isolates the math, so these tests run in milliseconds
 * and never depend on what's in the database.
 */
final class RecoveryCalculatorTest extends TestCase
{
    private const CHEST = [
        'id'             => 1,
        'muscle_key'     => 'lower_chest',
        'display_name'   => 'Lower Chest',
        'body_region'    => 'chest',
        'view_side'      => 'front',
        'recovery_hours' => 48,
    ];

    // ---------------- helpers ----------------

    /** Builds a calculator whose "database" contains exactly what we pass in. */
    private function calculator(array $sets = [], ?array $profile = null, array $activations = []): RecoveryCalculator
    {
        $muscleRepo = $this->createStub(MuscleRepository::class);
        $muscleRepo->method('getAll')->willReturn([self::CHEST]);

        $workoutRepo = $this->createStub(WorkoutRepository::class);
        $workoutRepo->method('getRecentSetsWithActivation')->willReturn($sets);

        $exerciseRepo = $this->createStub(ExerciseRepository::class);
        $exerciseRepo->method('getMuscleActivationsForExercises')->willReturn($activations);

        $profileRepo = $this->createStub(ProfileRepository::class);
        $profileRepo->method('getProfile')->willReturn($profile);

        return new RecoveryCalculator($muscleRepo, $workoutRepo, $exerciseRepo, $profileRepo);
    }

    /** One logged set hitting the chest, $hoursAgo hours ago. */
    private function set(float $hoursAgo, array $overrides = []): array
    {
        return array_merge([
            'muscle_id'         => 1,
            'workout_date'      => (new DateTime())->modify(sprintf('-%d minutes', (int) round($hoursAgo * 60)))->format('Y-m-d H:i:s'),
            'rpe'               => 9.0,
            'activation_weight' => 1.0,
            'weight_kg'         => 80,
            'is_bodyweight'     => 0,
        ], $overrides);
    }

    private function profile(array $overrides = []): array
    {
        return array_merge([
            'body_weight_kg' => 80.0,
            'activity_level' => 'moderately_active',
            'sex'            => 'male',
            'age'            => 25,
        ], $overrides);
    }

    private function chestFatigue(array $sets, ?array $profile = null): float
    {
        return $this->calculator($sets, $profile)->getAllMuscleReadiness(1)[0]['fatigue'];
    }

    // ---------------- baseline ----------------

    public function testGuestSeesEveryMuscleFullyRecovered(): void
    {
        $result = $this->calculator()->getAllMuscleReadiness(null)[0];

        $this->assertSame(100, $result['readiness']);
        $this->assertSame('Fully Recovered', $result['status']);
        $this->assertFalse($result['personalized']);
    }

    // ---------------- exact formula (pins docs/recovery-model.md) ----------------

    public function testOneMaxEffortSetRightNowCostsExactlyTwelvePoints(): void
    {
        // Fatigue = BaseFatigue(12) × RPE/10 (1.0) × Activation (1.0) × decay e^0 (1.0) = 12
        $result = $this->calculator([$this->set(0, ['rpe' => 10.0])])->getAllMuscleReadiness(1)[0];

        $this->assertEqualsWithDelta(12.0, $result['fatigue'], 0.1);
        $this->assertSame(88, $result['readiness']);
    }

    public function testAfterOneFullRecoveryWindowOnlyAboutFivePercentOfFatigueRemains(): void
    {
        // remaining = e^(-3 × t / recovery_hours); at t = 48 h → e^-3 ≈ 0.0498
        $fatigue = $this->chestFatigue([$this->set(48, ['rpe' => 10.0])]);

        $this->assertEqualsWithDelta(12.0 * exp(-3), $fatigue, 0.05);
    }

    public function testMissingRpeDefaultsToSeven(): void
    {
        $this->assertEqualsWithDelta(
            $this->chestFatigue([$this->set(1, ['rpe' => 7.0])]),
            $this->chestFatigue([$this->set(1, ['rpe' => null])]),
            0.01
        );
    }

    // ---------------- behaviour ----------------

    public function testRecentSessionLowersReadiness(): void
    {
        $result = $this->calculator([$this->set(1)])->getAllMuscleReadiness(1)[0];

        $this->assertLessThan(100, $result['readiness']);
        $this->assertGreaterThan(8.0, $result['fatigue']);
    }

    public function testMuscleIsBackAboveNinetyFivePercentAfterItsRecoveryWindow(): void
    {
        $result = $this->calculator([$this->set(50)])->getAllMuscleReadiness(1)[0];

        $this->assertGreaterThanOrEqual(95, $result['readiness']);
    }

    public function testHigherRpeCausesMoreFatigue(): void
    {
        $this->assertGreaterThan(
            $this->chestFatigue([$this->set(1, ['rpe' => 6.0])]),
            $this->chestFatigue([$this->set(1, ['rpe' => 10.0])])
        );
    }

    public function testFatigueAddsUpAcrossSets(): void
    {
        $one   = $this->chestFatigue([$this->set(1)]);
        $three = $this->chestFatigue([$this->set(1), $this->set(1), $this->set(1)]);

        $this->assertEqualsWithDelta($one * 3, $three, 0.2);
    }

    public function testFatigueIsCappedSoReadinessNeverGoesNegative(): void
    {
        $brutalSession = array_fill(0, 40, $this->set(0, ['rpe' => 10.0]));
        $result = $this->calculator($brutalSession)->getAllMuscleReadiness(1)[0];

        $this->assertSame(0, $result['readiness']);
        $this->assertSame('Fatigued — Rest Advised', $result['status']);
    }

    // ---------------- personalization ----------------

    public function testSameWeightTiresALighterPersonMore(): void
    {
        $set = $this->set(1, ['weight_kg' => 80]);

        $this->assertGreaterThan(
            $this->chestFatigue([$set], $this->profile(['body_weight_kg' => 100.0])),
            $this->chestFatigue([$set], $this->profile(['body_weight_kg' => 60.0]))
        );
    }

    public function testUnweightedBodyweightExerciseStillCountsAsLoad(): void
    {
        $pushUp = $this->set(1, ['is_bodyweight' => 1, 'weight_kg' => 0]);

        $this->assertGreaterThan(0, $this->chestFatigue([$pushUp], $this->profile()));
    }

    public function testOlderAthletesNeedLongerRecovery(): void
    {
        $young  = $this->calculator([], $this->profile(['age' => 22]))->getAllMuscleReadiness(1)[0];
        $senior = $this->calculator([], $this->profile(['age' => 55]))->getAllMuscleReadiness(1)[0];

        $this->assertGreaterThan($young['recovery_hours'], $senior['recovery_hours']);
    }

    public function testMoreActivePeopleRecoverFaster(): void
    {
        $sedentary = $this->calculator([], $this->profile(['activity_level' => 'sedentary']))->getAllMuscleReadiness(1)[0];
        $athlete   = $this->calculator([], $this->profile(['activity_level' => 'athlete']))->getAllMuscleReadiness(1)[0];

        $this->assertSame(55, $sedentary['recovery_hours']); // 48 × 1.15
        $this->assertSame(38, $athlete['recovery_hours']);   // 48 × 0.80
    }

    public function testNoProfileKeepsTheOriginalUnpersonalizedNumbers(): void
    {
        // Backward-compatibility guarantee: no profile → identical to the original calculator
        $result = $this->calculator([], null)->getAllMuscleReadiness(1)[0];

        $this->assertSame(48, $result['recovery_hours']);
        $this->assertFalse($result['personalized']);
    }

    // ---------------- planner ----------------

    public function testPlannerProjectsTheImpactOfAHypotheticalSession(): void
    {
        $calculator = $this->calculator([], null, [
            ['exercise_id' => 10, 'muscle_id' => 1, 'activation_weight' => 1.0, 'is_bodyweight' => 0],
        ]);
        $plan = [['exercise_id' => 10, 'rpe' => 8.5, 'weight_kg' => 100, 'reps' => 8]];

        $preview = $calculator->previewReadiness($plan, (new DateTime())->modify('+1 day'), 1)[0];

        $this->assertSame(100, $preview['readiness_before']);
        $this->assertLessThan(100, $preview['readiness_after']);
        $this->assertTrue($preview['affected']);
    }

    public function testPlannerLeavesUntouchedMusclesUnaffected(): void
    {
        $calculator = $this->calculator([], null, [
            ['exercise_id' => 10, 'muscle_id' => 99, 'activation_weight' => 1.0, 'is_bodyweight' => 0], // some other muscle
        ]);

        $preview = $calculator->previewReadiness([['exercise_id' => 10]], new DateTime(), 1)[0];

        $this->assertFalse($preview['affected']);
        $this->assertSame($preview['readiness_before'], $preview['readiness_after']);
    }
}