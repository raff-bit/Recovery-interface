<?php
/**
 * Demo data — creates an account with 3 weeks of realistic training.
 * ------------------------------------------------------------
 * Usage (from the project root):
 *   php database/seed_demo.php                      → demo@example.com
 *   php database/seed_demo.php someone@example.com  → custom email
 *
 * Push / Pull / Legs split, one rest day after each cycle, loads that
 * progress week over week. The most recent sessions are Push (this
 * morning) and Legs (yesterday), so the body map shows a realistic mix of
 * fatigued, recovering and fresh muscles.
 *
 * The password is RANDOM and printed once — no fixed demo credentials
 * ever live in the repository (lesson from the old legacy demo account).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../dal/UserRepository.php';
require_once __DIR__ . '/../dal/ProfileRepository.php';
require_once __DIR__ . '/../dal/WorkoutRepository.php';
require_once __DIR__ . '/../dal/RoutineRepository.php';

$email = strtolower($argv[1] ?? 'demo@example.com');

$users = new UserRepository();
if ($users->findByEmail($email)) {
    fwrite(STDERR, "An account with {$email} already exists — pick another email.\n");
    exit(1);
}

$db = Database::getConnection();
$exerciseId = function (string $name) use ($db): int {
    $stmt = $db->prepare('SELECT id FROM exercises WHERE name = ?');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        throw new RuntimeException("Exercise '{$name}' not found — run the seeds first.");
    }
    return (int) $id;
};

// [exercise, sets, reps, week-1 load in kg, weekly increase in kg, RPE]
$program = [
    'Legs' => [
        ['Squat',             4, 6,  90, 2.5, 8.5],
        ['Romanian Deadlift', 3, 8,  70, 2.5, 8.0],
        ['Leg Press',         3, 10, 140, 10, 8.0],
        ['Leg Curl',          3, 12, 40, 2.5, 7.5],
        ['Calf Raise',        4, 12, 60, 5,   8.0],
    ],
    'Push' => [
        ['Bench Press',          4, 6,  72.5, 2.5, 8.5],
        ['Incline Bench Press',  3, 8,  55,   2.5, 8.0],
        ['Overhead Press',       3, 8,  40,   2.5, 8.0],
        ['Lateral Raise',        3, 15, 8,    1,   9.0],
        ['Tricep Pushdown',      3, 12, 25,   2.5, 8.0],
    ],
    'Pull' => [
        ['Pull-ups',      4, 8,  0,  2.5, 8.5],
        ['Barbell Row',   4, 8,  65, 2.5, 8.0],
        ['Lat Pulldown',  3, 10, 55, 2.5, 7.5],
        ['Face Pull',     3, 15, 20, 2.5, 7.0],
        ['Bicep Curl',    3, 12, 12, 1,   8.5],
    ],
];

// Days ago → session (0 = today). Read bottom-up: oldest first. One rest day per cycle.
$schedule = [
    0 => 'Push', 1 => 'Legs', 2 => 'Pull',
    4 => 'Push', 5 => 'Legs', 6 => 'Pull',
    8 => 'Push', 9 => 'Legs', 10 => 'Pull',
    12 => 'Push', 13 => 'Legs', 14 => 'Pull',
    16 => 'Push', 17 => 'Legs', 18 => 'Pull',
];

$password = bin2hex(random_bytes(8));
$userId = $users->createUser('Demo Athlete', $email, $password);
(new ProfileRepository())->saveProfile($userId, 'male', 180.0, 80.0, 24, 'very_active');

$workouts = new WorkoutRepository();
$oldestCycle = max(array_keys($schedule));

foreach ($schedule as $daysAgo => $session) {
    // Progressive overload: the older the session, the lighter the load
    $weeksIn = intdiv($oldestCycle - $daysAgo, 4);
    // Anchored 3 hours before "now", so today's session is always in the past
    $date = (new DateTimeImmutable('-3 hours'))->modify("-{$daysAgo} days")->format('Y-m-d H:i:s');

    $sets = [];
    foreach ($program[$session] as [$name, $setCount, $reps, $load, $increase, $rpe]) {
        for ($s = 1; $s <= $setCount; $s++) {
            $sets[] = [
                'exercise_id' => $exerciseId($name),
                'set_number'  => $s,
                'reps'        => $reps,
                'weight_kg'   => $load + $weeksIn * $increase,
                'rpe'         => min(10.0, $rpe + ($s === $setCount ? 0.5 : 0)), // last set is the hardest
            ];
        }
    }

    $workouts->beginTransaction();
    $workoutId = $workouts->createWorkout($userId, $date, "{$session} day", null);
    $workouts->addSets($workoutId, $sets);
    $workouts->commit();
}

// One personal routine with today's loads, so "Your routines" isn't empty in the demo
$currentWeek = intdiv($oldestCycle, 4);
$items = [];
foreach ($program['Push'] as [$name, $setCount, $reps, $load, $increase, $rpe]) {
    $items[] = [
        'exercise_id' => $exerciseId($name),
        'sets'        => $setCount,
        'reps'        => $reps,
        'weight_kg'   => $load + $currentWeek * $increase,
        'rpe'         => $rpe,
    ];
}
(new RoutineRepository())->save($userId, null, 'My push (current loads)', $items);

printf("Demo account ready: %d workouts over %d days, 1 routine.\n", count($schedule), $oldestCycle);
echo "  Email:    {$email}\n";
echo "  Password: {$password}   (random — shown only now)\n";
