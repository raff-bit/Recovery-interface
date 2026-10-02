<?php
require_once __DIR__ . '/../dal/WorkoutRepository.php';

/**
 * AnalyticsService
 * ------------------------------------------------------------
 * Turns raw aggregate rows from the DAL into clean, chart-ready
 * data: fills in weeks with zero training so timelines don't have
 * gaps, and turns muscle-group counts into percentages.
 */
class AnalyticsService
{
    private WorkoutRepository $workoutRepo;

    public function __construct(WorkoutRepository $workoutRepo)
    {
        $this->workoutRepo = $workoutRepo;
    }

    /**
     * Total sets per week for the last $weeksBack weeks, oldest first.
     * Weeks with zero logged sets are included as 0, not omitted —
     * a chart needs a continuous timeline to be honest.
     */
    public function getWeeklyVolume(int $weeksBack = 8): array
    {
        $raw = $this->workoutRepo->getWeeklyVolume($weeksBack);

        $byWeek = [];
        foreach ($raw as $row) {
            $byWeek[$row['week_start']] = (int) $row['total_sets'];
        }

        $today = new DateTime();
        $dayOfWeek = (int) $today->format('N'); // 1 (Mon) .. 7 (Sun)
        $mondayThisWeek = (clone $today)->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);

        $result = [];
        for ($i = $weeksBack - 1; $i >= 0; $i--) {
            $weekStart = (clone $mondayThisWeek)->modify("-{$i} week");
            $key = $weekStart->format('Y-m-d');
            $result[] = [
                'week_start' => $key,
                'total_sets' => $byWeek[$key] ?? 0,
            ];
        }

        return $result;
    }

    /** Sets per body region over the last $daysBack days, with each region's share of the total. */
    public function getMuscleGroupBalance(int $daysBack = 30): array
    {
        $raw = $this->workoutRepo->getMuscleGroupSetCounts($daysBack);
        $total = array_sum(array_column($raw, 'total_sets'));

        $result = [];
        foreach ($raw as $row) {
            $count = (int) $row['total_sets'];
            $result[] = [
                'body_region' => $row['body_region'],
                'total_sets'  => $count,
                'percentage'  => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    public function getTopExercises(int $daysBack = 30, int $limit = 5): array
    {
        $raw = $this->workoutRepo->getTopExercises($daysBack, $limit);
        return array_map(function ($row) {
            return [
                'exercise_name' => $row['exercise_name'],
                'total_sets'    => (int) $row['total_sets'],
            ];
        }, $raw);
    }
}