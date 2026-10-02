<?php
require_once __DIR__ . '/../dal/WorkoutRepository.php';

class AnalyticsService
{
    private WorkoutRepository $workoutRepo;

    public function __construct(WorkoutRepository $workoutRepo)
    {
        $this->workoutRepo = $workoutRepo;
    }

    public function getWeeklyVolume(int $userId, int $weeksBack = 8): array
    {
        $raw = $this->workoutRepo->getWeeklyVolume($userId, $weeksBack);

        $byWeek = [];
        foreach ($raw as $row) {
            $byWeek[$row['week_start']] = (int) $row['total_sets'];
        }

        $today = new DateTime();
        $dayOfWeek = (int) $today->format('N');
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

    public function getMuscleGroupBalance(int $userId, int $daysBack = 30): array
    {
        $raw = $this->workoutRepo->getMuscleGroupSetCounts($userId, $daysBack);
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

    public function getTopExercises(int $userId, int $daysBack = 30, int $limit = 5): array
    {
        $raw = $this->workoutRepo->getTopExercises($userId, $daysBack, $limit);
        return array_map(function ($row) {
            return [
                'exercise_name' => $row['exercise_name'],
                'total_sets'    => (int) $row['total_sets'],
            ];
        }, $raw);
    }
}