-- ============================================================
-- MUSCLE RECOVERY TRACKER — Seed Data
-- Run this AFTER schema.sql
-- ============================================================

USE muscle_recovery_v2;

-- ------------------------------------------------------------
-- MUSCLES (17 groups)
-- ------------------------------------------------------------
INSERT INTO muscles (muscle_key, display_name, body_region, view_side, recovery_hours, sort_order) VALUES
('upper_chest',   'Upper Chest',       'chest',     'front', 48, 1),
('lower_chest',   'Lower Chest',       'chest',     'front', 48, 2),
('front_delts',   'Front Delts',       'shoulders', 'front', 48, 3),
('side_delts',    'Side Delts',        'shoulders', 'front', 48, 4),
('rear_delts',    'Rear Delts',        'shoulders', 'back',  48, 5),
('biceps',        'Biceps',            'arms',      'front', 36, 6),
('triceps',       'Triceps',           'arms',      'back',  48, 7),
('forearms',      'Forearms',          'arms',      'front', 24, 8),
('traps',         'Traps',             'back',      'back',  48, 9),
('lats',          'Lats',              'back',      'back',  60, 10),
('lower_back',    'Lower Back',        'back',      'back',  72, 11),
('abs',           'Abs',               'core',      'front', 24, 12),
('obliques',      'Obliques',          'core',      'front', 24, 13),
('quads',         'Quads',             'legs',      'front', 72, 14),
('hamstrings',    'Hamstrings',        'legs',      'back',  72, 15),
('glutes',        'Glutes',            'legs',      'back',  60, 16),
('calves',        'Calves',            'legs',      'front', 24, 17);

-- ------------------------------------------------------------
-- EXERCISES
-- ------------------------------------------------------------
INSERT INTO exercises (name, equipment, is_bodyweight) VALUES
('Bench Press',            'Barbell',    0),
('Incline Bench Press',    'Barbell',    0),
('Push-ups',               'Bodyweight', 1),
('Overhead Press',         'Barbell',    0),
('Lateral Raise',          'Dumbbell',   0),
('Rear Delt Fly',          'Dumbbell',   0),
('Pull-ups',               'Bodyweight', 1),
('Lat Pulldown',           'Machine',    0),
('Barbell Row',            'Barbell',    0),
('Deadlift',               'Barbell',    0),
('Bicep Curl',             'Dumbbell',   0),
('Hammer Curl',            'Dumbbell',   0),
('Tricep Pushdown',        'Cable',      0),
('Skull Crushers',         'Barbell',    0),
('Squat',                  'Barbell',    0),
('Leg Press',              'Machine',    0),
('Leg Extension',          'Machine',    0),
('Leg Curl',               'Machine',    0),
('Romanian Deadlift',      'Barbell',    0),
('Hip Thrust',             'Barbell',    0),
('Calf Raise',             'Machine',    0),
('Crunches',               'Bodyweight', 1),
('Plank',                  'Bodyweight', 1),
('Russian Twist',          'Bodyweight', 0),
('Shrugs',                 'Dumbbell',   0),
('Face Pull',              'Cable',      0);

-- ------------------------------------------------------------
-- EXERCISE_MUSCLES — activation mapping
-- ------------------------------------------------------------

-- Bench Press
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Bench Press' ex, 'lower_chest' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Bench Press','upper_chest',0.50,'secondary'
 UNION SELECT 'Bench Press','triceps',0.50,'secondary'
 UNION SELECT 'Bench Press','front_delts',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Incline Bench Press
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Incline Bench Press' ex, 'upper_chest' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Incline Bench Press','front_delts',0.50,'secondary'
 UNION SELECT 'Incline Bench Press','triceps',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Push-ups
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Push-ups' ex, 'lower_chest' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Push-ups','triceps',0.50,'secondary'
 UNION SELECT 'Push-ups','front_delts',0.40,'secondary'
 UNION SELECT 'Push-ups','abs',0.20,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Overhead Press
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Overhead Press' ex, 'front_delts' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Overhead Press','side_delts',0.50,'secondary'
 UNION SELECT 'Overhead Press','triceps',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Lateral Raise
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Lateral Raise' ex, 'side_delts' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Rear Delt Fly
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Rear Delt Fly' ex, 'rear_delts' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Pull-ups
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Pull-ups' ex, 'lats' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Pull-ups','biceps',0.50,'secondary'
 UNION SELECT 'Pull-ups','traps',0.30,'secondary'
 UNION SELECT 'Pull-ups','forearms',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Lat Pulldown
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Lat Pulldown' ex, 'lats' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Lat Pulldown','biceps',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Barbell Row
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Barbell Row' ex, 'lats' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Barbell Row','traps',0.50,'secondary'
 UNION SELECT 'Barbell Row','rear_delts',0.40,'secondary'
 UNION SELECT 'Barbell Row','biceps',0.30,'secondary'
 UNION SELECT 'Barbell Row','lower_back',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Deadlift
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Deadlift' ex, 'hamstrings' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Deadlift','glutes',0.80,'primary'
 UNION SELECT 'Deadlift','lower_back',0.70,'primary'
 UNION SELECT 'Deadlift','traps',0.40,'secondary'
 UNION SELECT 'Deadlift','forearms',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Bicep Curl
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Bicep Curl' ex, 'biceps' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Bicep Curl','forearms',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Hammer Curl
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Hammer Curl' ex, 'biceps' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Hammer Curl','forearms',0.60,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Tricep Pushdown
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Tricep Pushdown' ex, 'triceps' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Skull Crushers
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Skull Crushers' ex, 'triceps' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Squat
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Squat' ex, 'quads' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Squat','glutes',0.60,'secondary'
 UNION SELECT 'Squat','hamstrings',0.30,'secondary'
 UNION SELECT 'Squat','lower_back',0.20,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Leg Press
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Leg Press' ex, 'quads' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Leg Press','glutes',0.50,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Leg Extension
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Leg Extension' ex, 'quads' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Leg Curl
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Leg Curl' ex, 'hamstrings' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Romanian Deadlift
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Romanian Deadlift' ex, 'hamstrings' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Romanian Deadlift','glutes',0.60,'secondary'
 UNION SELECT 'Romanian Deadlift','lower_back',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Hip Thrust
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Hip Thrust' ex, 'glutes' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Hip Thrust','hamstrings',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Calf Raise
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Calf Raise' ex, 'calves' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Crunches
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Crunches' ex, 'abs' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Plank
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Plank' ex, 'abs' mu, 0.70 weight, 'primary' role
 UNION SELECT 'Plank','obliques',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Russian Twist
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Russian Twist' ex, 'obliques' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Russian Twist','abs',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Shrugs
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Shrugs' ex, 'traps' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Face Pull
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Face Pull' ex, 'rear_delts' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Face Pull','traps',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;