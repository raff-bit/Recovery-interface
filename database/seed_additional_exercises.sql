-- ============================================================
-- ADDITIONAL EXERCISES — Library Expansion
-- Safe to import any time — only adds new exercise names,
-- does not touch existing data.
-- ============================================================

USE muscle_recovery_v2;

INSERT INTO exercises (name, equipment, is_bodyweight) VALUES
('Cable Crossover',          'Cable',      0),
('Chest Dip',                'Bodyweight', 1),
('Close-Grip Bench Press',   'Barbell',    0),
('Diamond Push-ups',         'Bodyweight', 1),
('Preacher Curl',            'Barbell',    0),
('Concentration Curl',       'Dumbbell',   0),
('Zottman Curl',             'Dumbbell',   0),
('Overhead Tricep Extension','Dumbbell',   0),
('Seated Row',               'Cable',      0),
('T-Bar Row',                'Barbell',    0),
('Chin-ups',                 'Bodyweight', 1),
('Upright Row',              'Barbell',    0),
('Arnold Press',             'Dumbbell',   0),
('Cable Lateral Raise',      'Cable',      0),
('Reverse Fly',              'Dumbbell',   0),
('Bulgarian Split Squat',    'Dumbbell',   0),
('Walking Lunges',           'Dumbbell',   0),
('Goblet Squat',             'Dumbbell',   0),
('Sumo Deadlift',            'Barbell',    0),
('Glute Bridge',             'Barbell',    0),
('Seated Calf Raise',        'Machine',    0),
('Standing Calf Raise',      'Machine',    0),
('Ab Wheel Rollout',         'Bodyweight', 0),
('Hanging Leg Raise',        'Bodyweight', 1),
('Good Morning',             'Barbell',    0),
('Farmer''s Carry',          'Dumbbell',   0);

-- Cable Crossover
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Cable Crossover' ex, 'lower_chest' mu, 0.90 weight, 'primary' role
 UNION SELECT 'Cable Crossover','upper_chest',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Chest Dip
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Chest Dip' ex, 'lower_chest' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Chest Dip','triceps',0.50,'secondary'
 UNION SELECT 'Chest Dip','front_delts',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Close-Grip Bench Press
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Close-Grip Bench Press' ex, 'triceps' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Close-Grip Bench Press','lower_chest',0.40,'secondary'
 UNION SELECT 'Close-Grip Bench Press','front_delts',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Diamond Push-ups
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Diamond Push-ups' ex, 'triceps' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Diamond Push-ups','lower_chest',0.50,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Preacher Curl
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Preacher Curl' ex, 'biceps' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Concentration Curl
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Concentration Curl' ex, 'biceps' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Zottman Curl
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Zottman Curl' ex, 'biceps' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Zottman Curl','forearms',0.60,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Overhead Tricep Extension
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Overhead Tricep Extension' ex, 'triceps' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Seated Row
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Seated Row' ex, 'lats' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Seated Row','traps',0.40,'secondary'
 UNION SELECT 'Seated Row','biceps',0.30,'secondary'
 UNION SELECT 'Seated Row','rear_delts',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- T-Bar Row
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'T-Bar Row' ex, 'lats' mu, 0.90 weight, 'primary' role
 UNION SELECT 'T-Bar Row','traps',0.40,'secondary'
 UNION SELECT 'T-Bar Row','biceps',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Chin-ups
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Chin-ups' ex, 'lats' mu, 0.90 weight, 'primary' role
 UNION SELECT 'Chin-ups','biceps',0.60,'primary'
 UNION SELECT 'Chin-ups','forearms',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Upright Row
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Upright Row' ex, 'side_delts' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Upright Row','traps',0.50,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Arnold Press
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Arnold Press' ex, 'front_delts' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Arnold Press','side_delts',0.50,'secondary'
 UNION SELECT 'Arnold Press','triceps',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Cable Lateral Raise
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Cable Lateral Raise' ex, 'side_delts' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Reverse Fly
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Reverse Fly' ex, 'rear_delts' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Reverse Fly','traps',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Bulgarian Split Squat
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Bulgarian Split Squat' ex, 'quads' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Bulgarian Split Squat','glutes',0.60,'secondary'
 UNION SELECT 'Bulgarian Split Squat','hamstrings',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Walking Lunges
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Walking Lunges' ex, 'quads' mu, 0.90 weight, 'primary' role
 UNION SELECT 'Walking Lunges','glutes',0.60,'secondary'
 UNION SELECT 'Walking Lunges','hamstrings',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Goblet Squat
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Goblet Squat' ex, 'quads' mu, 0.90 weight, 'primary' role
 UNION SELECT 'Goblet Squat','glutes',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Sumo Deadlift
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Sumo Deadlift' ex, 'glutes' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Sumo Deadlift','hamstrings',0.70,'primary'
 UNION SELECT 'Sumo Deadlift','quads',0.40,'secondary'
 UNION SELECT 'Sumo Deadlift','lower_back',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Glute Bridge
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Glute Bridge' ex, 'glutes' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Glute Bridge','hamstrings',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Seated Calf Raise
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Seated Calf Raise' ex, 'calves' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Standing Calf Raise
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Standing Calf Raise' ex, 'calves' mu, 1.00 weight, 'primary' role
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Ab Wheel Rollout
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Ab Wheel Rollout' ex, 'abs' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Ab Wheel Rollout','obliques',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Hanging Leg Raise
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Hanging Leg Raise' ex, 'abs' mu, 1.00 weight, 'primary' role
 UNION SELECT 'Hanging Leg Raise','obliques',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Good Morning
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Good Morning' ex, 'hamstrings' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Good Morning','lower_back',0.60,'primary'
 UNION SELECT 'Good Morning','glutes',0.40,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;

-- Farmer's Carry
INSERT INTO exercise_muscles (exercise_id, muscle_id, activation_weight, role)
SELECT e.id, m.id, v.weight, v.role FROM exercises e, muscles m,
(SELECT 'Farmer''s Carry' ex, 'forearms' mu, 0.80 weight, 'primary' role
 UNION SELECT 'Farmer''s Carry','traps',0.50,'secondary'
 UNION SELECT 'Farmer''s Carry','abs',0.30,'secondary'
) v WHERE e.name = v.ex AND m.muscle_key = v.mu;