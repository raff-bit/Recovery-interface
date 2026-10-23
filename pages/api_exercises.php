<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../dal/ExerciseRepository.php';

$exerciseRepo = new ExerciseRepository();

$exercises = $exerciseRepo->getAll();
$activations = $exerciseRepo->getAllMuscleActivations();

// Group activations by exercise_id so each exercise carries its own muscle list
$grouped = [];
foreach ($activations as $row) {
    $grouped[$row['exercise_id']][] = [
        'muscle_key'        => $row['muscle_key'],
        'display_name'      => $row['display_name'],
        'body_region'       => $row['body_region'],
        'activation_weight' => (float) $row['activation_weight'],
        'role'              => $row['role'],
    ];
}

foreach ($exercises as &$ex) {
    $ex['muscles']       = $grouped[$ex['id']] ?? [];
    $ex['is_bodyweight'] = (bool) $ex['is_bodyweight'];
}
unset($ex);

echo json_encode(['exercises' => $exercises]);