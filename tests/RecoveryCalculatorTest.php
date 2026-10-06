<?php
/**
 * Test Suite — RecoveryCalculator Mathematical & Biomechanical Verification
 * Run from terminal: php tests/RecoveryCalculatorTest.php
 */

require_once __DIR__ . '/../dal/MuscleRepository.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';
require_once __DIR__ . '/../dal/ExerciseRepository.php';
require_once __DIR__ . '/../dal/ProfileRepository.php';
require_once __DIR__ . '/../bll/RecoveryCalculator.php';

// ============================================================
// IN-MEMORY MOCK REPOSITORIES (Zero Database Dependency)
// ============================================================

class MockMuscleRepository extends MuscleRepository
{
    private array $muscles;
    public function __construct(array $muscles = []) { $this->muscles = $muscles; }
    public function getAll(): array { return $this->muscles; }
}

class MockWorkoutRepository extends WorkoutRepository
{
    private array $sets;
    public function __construct(array $sets = []) { $this->sets = $sets; }
    public function getRecentSetsWithActivation(int $userId, int $days): array { return $this->sets; }
}

class MockExerciseRepository extends ExerciseRepository
{
    private array $activations;
    public function __construct(array $activations = []) { $this->activations = $activations; }
    public function getMuscleActivationsForExercises(array $exerciseIds): array { return $this->activations; }
}

class MockProfileRepository extends ProfileRepository
{
    private ?array $profile;
    public function __construct(?array $profile = null) { $this->profile = $profile; }
    public function getProfile(int $userId): ?array { return $this->profile; }
}

// ============================================================
// TEST RUNNER ENGINE
// ============================================================

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function assert(bool $condition, string $testName, string $details = ''): void
    {
        if ($condition) {
            echo "  \033[32m✔ PASS\033[0m: {$testName}\n";
            $this->passed++;
        } else {
            echo "  \033[31m✖ FAIL\033[0m: {$testName}" . ($details ? " ({$details})" : "") . "\n";
            $this->failed++;
        }
    }

    public function summary(): void
    {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed.\n";
        echo str_repeat('=', 50) . "\n";
        if ($this->failed > 0) exit(1);
    }
}

$t = new TestRunner();
echo "\n--- RUNNING RECOVERY CALCULATOR UNIT TESTS ---\n\n";

$chestMuscle = [
    'id'             => 1,
    'muscle_key'     => 'lower_chest',
    'display_name'   => 'Lower Chest',
    'body_region'    => 'chest',
    'view_side'      => 'front',
    'recovery_hours' => 48,
];

// ------------------------------------------------------------
// TEST 1: Baseline for Guest / Unauthenticated User
// ------------------------------------------------------------
$calc = new RecoveryCalculator(
    new MockMuscleRepository([$chestMuscle]),
    new MockWorkoutRepository([]),
    new MockExerciseRepository([]),
    new MockProfileRepository(null)
);
$res = $calc->getAllMuscleReadiness(null);
$t->assert($res[0]['readiness'] === 100, 'Guest user readiness is 100%');
$t->assert($res[0]['status'] === 'Optimal' || $res[0]['status'] === 'Fully Recovered', 'Guest status is optimal');

// ------------------------------------------------------------
// TEST 2: Recent Workout Induces High Fatigue
// ------------------------------------------------------------
$oneHourAgo = (new DateTime())->modify('-1 hour')->format('Y-m-d H:i:s');
$recentSet = [
    'muscle_id'         => 1,
    'workout_date'      => $oneHourAgo,
    'rpe'               => 9.0,
    'activation_weight' => 1.0,
    'weight_kg'         => 80,
    'is_bodyweight'     => 0,
];
$calcRecent = new RecoveryCalculator(
    new MockMuscleRepository([$chestMuscle]),
    new MockWorkoutRepository([$recentSet]),
    new MockExerciseRepository([]),
    new MockProfileRepository(null)
);
$resRecent = $calcRecent->getAllMuscleReadiness(1);
$t->assert($resRecent[0]['readiness'] < 100, 'Recent session lowers muscle readiness');
$t->assert($resRecent[0]['fatigue'] > 8.0, 'Fatigue is correctly accumulated from high RPE set');

// ------------------------------------------------------------
// TEST 3: Exponential Decay Recovers Muscle After 48+ Hours
// ------------------------------------------------------------
$twoDaysAgo = (new DateTime())->modify('-50 hours')->format('Y-m-d H:i:s');
$oldSet = [
    'muscle_id'         => 1,
    'workout_date'      => $twoDaysAgo,
    'rpe'               => 9.0,
    'activation_weight' => 1.0,
    'weight_kg'         => 80,
    'is_bodyweight'     => 0,
];
$calcOld = new RecoveryCalculator(
    new MockMuscleRepository([$chestMuscle]),
    new MockWorkoutRepository([$oldSet]),
    new MockExerciseRepository([]),
    new MockProfileRepository(null)
);
$resOld = $calcOld->getAllMuscleReadiness(1);
$t->assert($resOld[0]['readiness'] >= 95, 'Muscle recovers to >=95% readiness after its full recovery_hours');

// ------------------------------------------------------------
// TEST 4: RPE Sensitivity (RPE 10 generates more fatigue than RPE 6)
// ------------------------------------------------------------
$setRpe6 = array_merge($recentSet, ['rpe' => 6.0]);
$setRpe10 = array_merge($recentSet, ['rpe' => 10.0]);

$calcRpe6 = new RecoveryCalculator(new MockMuscleRepository([$chestMuscle]), new MockWorkoutRepository([$setRpe6]), new MockExerciseRepository([]), new MockProfileRepository(null));
$calcRpe10 = new RecoveryCalculator(new MockMuscleRepository([$chestMuscle]), new MockWorkoutRepository([$setRpe10]), new MockExerciseRepository([]), new MockProfileRepository(null));

$fatigue6 = $calcRpe6->getAllMuscleReadiness(1)[0]['fatigue'];
$fatigue10 = $calcRpe10->getAllMuscleReadiness(1)[0]['fatigue'];
$t->assert($fatigue10 > $fatigue6, 'RPE 10 induces higher fatigue than RPE 6');

// ------------------------------------------------------------
// TEST 5: Volume Scaling (3 sets cause proportionally more fatigue than 1 set)
// ------------------------------------------------------------
$calc1Set = new RecoveryCalculator(new MockMuscleRepository([$chestMuscle]), new MockWorkoutRepository([$recentSet]), new MockExerciseRepository([]), new MockProfileRepository(null));
$calc3Sets = new RecoveryCalculator(new MockMuscleRepository([$chestMuscle]), new MockWorkoutRepository([$recentSet, $recentSet, $recentSet]), new MockExerciseRepository([]), new MockProfileRepository(null));

$f1 = $calc1Set->getAllMuscleReadiness(1)[0]['fatigue'];
$f3 = $calc3Sets->getAllMuscleReadiness(1)[0]['fatigue'];
$t->assert(abs($f3 - ($f1 * 3)) <= 0.2, 'Fatigue accumulates across multiple sets performed in the session');
// ------------------------------------------------------------
// TEST 6: Relative Load Scaling With User Profile Bodyweight
// ------------------------------------------------------------
$profileLight = ['body_weight_kg' => 60.0, 'activity_level' => 'moderately_active', 'sex' => 'male', 'age' => 25];
$profileHeavy = ['body_weight_kg' => 100.0, 'activity_level' => 'moderately_active', 'sex' => 'male', 'age' => 25];
$heavyLiftSet = array_merge($recentSet, ['weight_kg' => 80]); // 80kg is 1.33x for light person, but only 0.8x for heavy person

$calcLight = new RecoveryCalculator(new MockMuscleRepository([$chestMuscle]), new MockWorkoutRepository([$heavyLiftSet]), new MockExerciseRepository([]), new MockProfileRepository($profileLight));
$calcHeavy = new RecoveryCalculator(new MockMuscleRepository([$chestMuscle]), new MockWorkoutRepository([$heavyLiftSet]), new MockExerciseRepository([]), new MockProfileRepository($profileHeavy));

$fLight = $calcLight->getAllMuscleReadiness(1)[0]['fatigue'];
$fHeavy = $calcHeavy->getAllMuscleReadiness(1)[0]['fatigue'];
$t->assert($fLight > $fHeavy, 'A fixed external weight induces greater relative fatigue in a lighter individual');

// ------------------------------------------------------------
// TEST 7: Bodyweight Exercises Count Bodyweight as Effective Load
// ------------------------------------------------------------
$bwSetUnweighted = array_merge($recentSet, ['is_bodyweight' => 1, 'weight_kg' => 0]);
$calcBw = new RecoveryCalculator(new MockMuscleRepository([$chestMuscle]), new MockWorkoutRepository([$bwSetUnweighted]), new MockExerciseRepository([]), new MockProfileRepository($profileHeavy));
$fBw = $calcBw->getAllMuscleReadiness(1)[0]['fatigue'];
$t->assert($fBw > 0, 'Unweighted bodyweight exercises are not treated as 0 kg load');

// ------------------------------------------------------------
// TEST 8: Age Biometric Adjustment (Older age requires more recovery time)
// ------------------------------------------------------------
$profileYoung = ['body_weight_kg' => 80.0, 'activity_level' => 'moderately_active', 'sex' => 'male', 'age' => 22];
$profileSenior = ['body_weight_kg' => 80.0, 'activity_level' => 'moderately_active', 'sex' => 'male', 'age' => 55];

$calcYoung = new RecoveryCalculator(new MockMuscleRepository([$chestMuscle]), new MockWorkoutRepository([]), new MockExerciseRepository([]), new MockProfileRepository($profileYoung));
$calcSenior = new RecoveryCalculator(new MockMuscleRepository([$chestMuscle]), new MockWorkoutRepository([]), new MockExerciseRepository([]), new MockProfileRepository($profileSenior));

$hoursYoung = $calcYoung->getAllMuscleReadiness(1)[0]['recovery_hours'];
$hoursSenior = $calcSenior->getAllMuscleReadiness(1)[0]['recovery_hours'];
$t->assert($hoursSenior > $hoursYoung, 'Age-based multiplier increases effective recovery hours for masters athletes');

// ------------------------------------------------------------
// TEST 9: Predictive Engine (Session Planner Simulation)
// ------------------------------------------------------------
$futureDate = (new DateTime())->modify('+1 day');
$hypoSets = [
    ['exercise_id' => 10, 'rpe' => 8.5, 'weight_kg' => 100, 'reps' => 8]
];
$mockExerciseRepo = new MockExerciseRepository([
    ['exercise_id' => 10, 'muscle_id' => 1, 'activation_weight' => 1.0, 'is_bodyweight' => 0]
]);

$calcSim = new RecoveryCalculator(new MockMuscleRepository([$chestMuscle]), new MockWorkoutRepository([]), $mockExerciseRepo, new MockProfileRepository(null));
$preview = $calcSim->previewReadiness($hypoSets, $futureDate, 1);

$t->assert($preview[0]['readiness_before'] === 100, 'Planner projects baseline readiness before planned session');
$t->assert($preview[0]['readiness_after'] < 100, 'Planner projects fatigue impact after hypothetical sets');
$t->assert($preview[0]['affected'] === true, 'Target muscle correctly flagged as affected by hypothetical exercise');

$t->summary();