/* ============================================================
   RECOVERY INTERFACE — Frontend Logic
   Talks to pages/*.php only through fetch(). No SQL. No fatigue
   math lives here — the backend already did that work; this file
   just displays it and collects new workout data.
   ============================================================ */

/* ---------- STATE ---------- */

const state = {
  muscles: [],       // latest readiness data from api_muscles.php
  exercises: [],      // exercise library from api_exercises.php
  profile: null,
  hologramView: 'front', // 'front' | 'back'
  unitSystem: localStorage.getItem('recovery_unit_system') || 'metric', // 'metric'
  activePickerRow: null,  // which set-row is currently choosing an exercise
  activePickerRegion: 'all',
  editingWorkoutId: null, // set when the Log Workout form is editing a past session instead of creating a new one
};

const MUSCLE_GROUPS = [
  { key: 'all',       label: 'All' },
  { key: 'chest',     label: 'Chest' },
  { key: 'back',      label: 'Back' },
  { key: 'shoulders', label: 'Shoulders' },
  { key: 'arms',      label: 'Arms' },
  { key: 'core',      label: 'Core' },
  { key: 'legs',      label: 'Legs' },
];

/* ---------- UNIT CONVERSIONS ---------- */

const LBS_PER_KG = 2.20462;
const IN_PER_CM = 0.393701;

function kgToDisplayWeight(kg) {
  if (kg === null || kg === undefined || isNaN(kg)) return '';
  return state.unitSystem === 'imperial'
    ? Math.round(kg * LBS_PER_KG * 10) / 10
    : Math.round(kg * 10) / 10;
}

function displayWeightToKg(val) {
  if (!val || isNaN(val)) return 0;
  return state.unitSystem === 'imperial'
    ? Math.round((val / LBS_PER_KG) * 10) / 10
    : Math.round(val * 10) / 10;
}

function cmToDisplayHeight(cm) {
  if (cm === null || cm === undefined || isNaN(cm)) return '';
  return state.unitSystem === 'imperial'
    ? Math.round(cm * IN_PER_CM * 10) / 10
    : Math.round(cm * 10) / 10;
}

function displayHeightToCm(val) {
  if (!val || isNaN(val)) return null;
  return state.unitSystem === 'imperial'
    ? Math.round((val / IN_PER_CM) * 10) / 10
    : Math.round(val * 10) / 10;
}

function getWeightUnitLabel() {
  return state.unitSystem === 'imperial' ? 'lbs' : 'kg';
}

/* ---------- API CALLS ---------- */

async function fetchMuscles() {
  const res = await fetch('pages/api_muscles.php');
  if (!res.ok) throw new Error('Failed to load muscle data');
  return res.json();
}

async function fetchExercises() {
  const res = await fetch('pages/api_exercises.php');
  if (!res.ok) throw new Error('Failed to load exercise library');
  return res.json();
}

async function fetchHistory() {
  const res = await fetch('pages/api_workout_history.php?limit=9');
  if (!res.ok) throw new Error('Failed to load workout history');
  return res.json();
}

async function postWorkout(payload) {
  const res = await fetch('pages/api_log_workout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to save workout');
  return data;
}

async function postPreview(payload) {
  const res = await fetch('pages/api_preview_workout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to preview workout');
  return data;
}

async function fetchWorkoutById(workoutId) {
  const res = await fetch(`pages/api_get_workout.php?id=${workoutId}`);
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to load workout');
  return data.workout;
}

async function updateWorkout(workoutId, payload) {
  const res = await fetch('pages/api_update_workout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ workout_id: workoutId, ...payload }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to update workout');
  return data;
}

async function deleteWorkoutApi(workoutId) {
  const res = await fetch('pages/api_delete_workout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ workout_id: workoutId }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to delete workout');
  return data;
}

async function fetchProfile() {
  const res = await fetch('pages/api_get_profile.php');
  if (!res.ok) throw new Error('Failed to load profile');
  return res.json();
}

async function saveProfileApi(payload) {
  const res = await fetch('pages/api_save_profile.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to save profile');
  return data;
}

/* ---------- RENDERING: MUSCLE STATUS LIST ---------- */

function renderMuscleList(muscles) {
  const list = document.getElementById('muscle-list');
  list.innerHTML = '';

  muscles.forEach(m => {
    const li = document.createElement('li');
    li.className = 'muscle-row';
    li.dataset.muscle = m.muscle_key;

    li.innerHTML = `
      <span class="muscle-row-name">${m.display_name}</span>
      <span class="muscle-row-status" style="color:${m.status_color}">${m.status} · ${m.readiness}%</span>
      <div class="muscle-row-bar-track">
        <div class="muscle-row-bar-fill" style="width:${m.readiness}%; background:${m.status_color}"></div>
      </div>
    `;
    list.appendChild(li);
  });
}

/* ---------- RENDERING: HOLOGRAM COLORS ---------- */

function applyHologramColors(muscles) {
  muscles.forEach(m => {
    const shapes = document.querySelectorAll(`.muscle-shape[data-muscle="${m.muscle_key}"]`);
    const glowStrength = 4 + ((100 - m.readiness) / 100) * 10;

    shapes.forEach(shape => {
      shape.style.fill = m.status_color;
      shape.style.filter = `drop-shadow(0 0 ${glowStrength}px ${m.status_color})`;
      shape.classList.toggle('critical-pulse', m.readiness < 35);
    });
  });
}

/* ---------- RENDERING: WORKOUT HISTORY ---------- */

function renderHistory(workouts) {
  const list = document.getElementById('history-list');
  list.innerHTML = '';

  if (workouts.length === 0) {
    list.innerHTML = '<p class="hologram-footnote">No sessions logged yet.</p>';
    return;
  }

  workouts.forEach(w => {
    const bySets = {};
    w.sets.forEach(s => {
      const displayWeight = kgToDisplayWeight(s.weight_kg);
      const unit = getWeightUnitLabel();
      const line = `${s.reps} reps${s.weight_kg > 0 ? ' @ ' + displayWeight + unit : ''}`;
      (bySets[s.exercise_name] = bySets[s.exercise_name] || []).push(line);
    });

    const setsSummary = Object.entries(bySets)
      .map(([name, lines]) => `<div><strong>${name}:</strong> ${lines.join(', ')}</div>`)
      .join('');

    const date = new Date(w.workout_date.replace(' ', 'T'));
    const dateStr = date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

    const li = document.createElement('li');
    li.className = 'history-card';
    li.innerHTML = `
      <div class="history-card-title">${w.label || 'Workout'}</div>
      <div class="history-card-date">${dateStr}</div>
      <div class="history-card-sets">${setsSummary}</div>
      <div class="history-card-actions">
        <button type="button" class="history-action-btn edit-workout-btn">Edit</button>
        <button type="button" class="history-action-btn delete-workout-btn">Delete</button>
      </div>
    `;
    li.querySelector('.edit-workout-btn').addEventListener('click', () => handleEditWorkout(w.id));
    li.querySelector('.delete-workout-btn').addEventListener('click', () => handleDeleteWorkout(w.id));
    list.appendChild(li);
  });
}

/* ---------- EDIT / DELETE PAST SESSIONS ---------- */

/** Pulls a past workout's sets back into the Log Workout form and switches it into "editing" mode. */
function enterEditMode(workout) {
  state.editingWorkoutId = workout.id;

  document.getElementById('log-panel-label').textContent = 'Edit Workout';
  document.getElementById('save-workout-btn').textContent = 'Update Workout';
  document.getElementById('cancel-edit-btn').style.display = 'inline-block';

  document.getElementById('workout-label').value = workout.label || '';

  const container = document.getElementById('sets-container');
  container.innerHTML = '';

  workout.sets.forEach(s => {
    addSetRow();
    const rows = container.querySelectorAll('.set-row');
    const row = rows[rows.length - 1];

    const exercise = state.exercises.find(ex => ex.id === s.exercise_id);
    const btn = row.querySelector('.exercise-picker-btn');
    const hiddenInput = row.querySelector('.set-exercise');

    hiddenInput.value = s.exercise_id;
    btn.textContent = s.exercise_name;
    btn.classList.add('selected');
    if (exercise) {
      hiddenInput.dataset.equipment = exercise.equipment || '';
      hiddenInput.dataset.bodyweight = exercise.is_bodyweight ? '1' : '0';
    }

    row.querySelector('.set-reps').value = s.reps;
    row.querySelector('.set-weight').value = s.weight_kg ? kgToDisplayWeight(s.weight_kg) : '';
    row.querySelector('.set-rpe').value = s.rpe ?? '';
  });

  document.querySelector('.log-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function exitEditMode() {
  state.editingWorkoutId = null;
  document.getElementById('log-panel-label').textContent = 'Log Workout';
  document.getElementById('save-workout-btn').textContent = 'Save Workout';
  document.getElementById('cancel-edit-btn').style.display = 'none';
  document.getElementById('workout-form').reset();
  document.getElementById('sets-container').innerHTML = '';
  addSetRow();
}

async function handleEditWorkout(workoutId) {
  try {
    const workout = await fetchWorkoutById(workoutId);
    enterEditMode(workout);
  } catch (err) {
    console.error(err);
    alert(err.message);
  }
}

async function handleDeleteWorkout(workoutId) {
  if (!confirm('Delete this workout? This cannot be undone.')) return;

  try {
    await deleteWorkoutApi(workoutId);
    if (state.editingWorkoutId === workoutId) {
      exitEditMode();
    }
    await refreshHistory();
    await refreshMuscles();
  } catch (err) {
    console.error(err);
    alert(err.message);
  }
}

/* ---------- EXERCISE PICKER MODAL ---------- */

/**
 * An exercise can hit several muscles across several regions
 * (e.g. Bench Press: chest primary, triceps + shoulders secondary).
 * For grouping purposes we use whichever "primary" muscle has the
 * highest activation weight. If somehow none are marked primary,
 * fall back to the first muscle listed.
 */
function getExercisePrimaryRegion(exercise) {
  if (!exercise.muscles || exercise.muscles.length === 0) return 'other';

  const primaries = exercise.muscles.filter(m => m.role === 'primary');
  const pool = primaries.length > 0 ? primaries : exercise.muscles;

  const top = pool.reduce((best, m) =>
    m.activation_weight > best.activation_weight ? m : best
  , pool[0]);

  return top.body_region;
}

function openExercisePicker(rowElement) {
  state.activePickerRow = rowElement;
  state.activePickerRegion = 'all';
  renderPickerTabs();
  renderPickerList();
  document.getElementById('exercise-picker-modal').style.display = 'flex';
}

function closeExercisePicker() {
  document.getElementById('exercise-picker-modal').style.display = 'none';
  state.activePickerRow = null;
}

function renderPickerTabs() {
  const container = document.getElementById('picker-tabs');
  container.innerHTML = '';

  MUSCLE_GROUPS.forEach(group => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'picker-tab' + (state.activePickerRegion === group.key ? ' active' : '');
    btn.textContent = group.label;
    btn.addEventListener('click', () => {
      state.activePickerRegion = group.key;
      renderPickerTabs();
      renderPickerList();
    });
    container.appendChild(btn);
  });
}

function renderPickerList() {
  const list = document.getElementById('picker-list');
  list.innerHTML = '';

  const region = state.activePickerRegion;
  const filtered = region === 'all'
    ? state.exercises
    : state.exercises.filter(ex => getExercisePrimaryRegion(ex) === region);

  if (filtered.length === 0) {
    list.innerHTML = '<p class="hologram-footnote">No exercises in this group.</p>';
    return;
  }

  filtered
    .slice()
    .sort((a, b) => a.name.localeCompare(b.name))
    .forEach(ex => {
      const li = document.createElement('li');
      li.className = 'picker-item';
      li.innerHTML = `
        <span class="picker-item-name">${ex.name}</span>
        <span class="picker-item-meta">${ex.equipment || ''}</span>
      `;
      li.addEventListener('click', () => selectExerciseForRow(ex));
      list.appendChild(li);
    });
}

function selectExerciseForRow(exercise) {
  const row = state.activePickerRow;
  if (!row) return;

  const btn = row.querySelector('.exercise-picker-btn');
  const hiddenInput = row.querySelector('.set-exercise');

  btn.textContent = exercise.name;
  btn.classList.add('selected');
  hiddenInput.value = exercise.id;
  hiddenInput.dataset.equipment = exercise.equipment || '';
  hiddenInput.dataset.bodyweight = exercise.is_bodyweight ? '1' : '0';

  closeExercisePicker();
}

/* ---------- WEIGHT INPUT HELP ---------- */

const EQUIPMENT_HELP = {
  'Barbell':    'Enter the TOTAL weight lifted, including the bar itself (e.g. bar + all plates).',
  'Dumbbell':   'Enter the weight of ONE dumbbell — not the combined weight of both.',
  'Machine':    "Enter the weight shown on the machine's stack or pin setting.",
  'Cable':      'Enter the weight set on the cable stack.',
  'Bodyweight': 'Leave at 0 for bodyweight only, or enter any added weight (vest, belt, plate).',
};

function getWeightHelpText(row) {
  const hiddenInput = row.querySelector('.set-exercise');
  const equipment = hiddenInput ? hiddenInput.dataset.equipment : '';

  if (!equipment) {
    return 'Choose an exercise first — this tip adjusts to how that equipment is usually logged.';
  }
  return EQUIPMENT_HELP[equipment] || 'Enter the weight used for this exercise.';
}

function toggleWeightHelp(event, row) {
  const tooltip = document.getElementById('weight-help-tooltip');
  const btn = event.currentTarget;

  if (tooltip.style.display === 'block' && tooltip._openBtn === btn) {
    closeWeightHelp();
    return;
  }

  tooltip.textContent = getWeightHelpText(row);
  tooltip.style.display = 'block';
  tooltip._openBtn = btn;

  const rect = btn.getBoundingClientRect();
  const tooltipWidth = 240;
  let left = rect.left + rect.width / 2 - tooltipWidth / 2;
  left = Math.max(8, Math.min(left, window.innerWidth - tooltipWidth - 8));
  const top = rect.bottom + 8;

  tooltip.style.left = left + 'px';
  tooltip.style.top = top + 'px';
}

function closeWeightHelp() {
  const tooltip = document.getElementById('weight-help-tooltip');
  tooltip.style.display = 'none';
  tooltip._openBtn = null;
}

/* ---------- SESSION PLANNER ---------- */

function addPlannerSetRow() {
  const container = document.getElementById('planner-sets-container');
  const row = document.createElement('div');
  row.className = 'set-row planner-set-row';
  row.innerHTML = `
    <button type="button" class="exercise-picker-btn">Choose Exercise</button>
    <input type="hidden" class="set-exercise" value="">
    <input type="number" class="set-count" placeholder="Sets" min="1" value="1">
    <input type="number" class="set-rpe" placeholder="RPE" min="1" max="10" step="0.5">
    <button type="button" class="remove-set-btn" title="Remove">×</button>
  `;
  row.querySelector('.exercise-picker-btn').addEventListener('click', () => openExercisePicker(row));
  row.querySelector('.remove-set-btn').addEventListener('click', () => row.remove());
  container.appendChild(row);
}

function collectPlannerSets() {
  const rows = document.querySelectorAll('#planner-sets-container .planner-set-row');
  const sets = [];

  rows.forEach(row => {
    const exerciseId = row.querySelector('.set-exercise').value;
    const count = parseInt(row.querySelector('.set-count').value, 10) || 1;
    const rpe = row.querySelector('.set-rpe').value;

    if (!exerciseId) return;

    for (let i = 0; i < count; i++) {
      sets.push({
        exercise_id: parseInt(exerciseId, 10),
        rpe: rpe ? parseFloat(rpe) : null,
      });
    }
  });

  return sets;
}

function showPlannerFeedback(message, isError) {
  const el = document.getElementById('planner-feedback');
  el.textContent = message;
  el.className = 'log-feedback ' + (isError ? 'error' : 'success');
}

function renderPlannerResults(muscles, targetDateIso) {
  const list = document.getElementById('planner-results');
  list.innerHTML = '';

  const affected = muscles.filter(m => m.affected);

  if (affected.length === 0) {
    list.innerHTML = '<p class="hologram-footnote">No tracked muscles matched that exercise selection.</p>';
    return;
  }

  const dateLabel = new Date(targetDateIso).toLocaleDateString(undefined, {
    weekday: 'short', month: 'short', day: 'numeric',
  });

  const heading = document.createElement('p');
  heading.className = 'hologram-footnote';
  heading.textContent = `Projected for ${dateLabel}:`;
  list.appendChild(heading);

  affected.forEach(m => {
    const li = document.createElement('li');
    li.className = 'muscle-row planner-result-row';
    li.innerHTML = `
      <span class="muscle-row-name">${m.display_name}</span>
      <span class="planner-result-values">
        <span style="color:${m.status_color_before}">${m.readiness_before}%</span>
        →
        <span style="color:${m.status_color_after}">${m.readiness_after}%</span>
      </span>
    `;
    list.appendChild(li);
  });
}

/** Temporarily tints only the AFFECTED muscles on the hologram to their projected "after" color. */
function applyPreviewColors(muscles) {
  muscles.filter(m => m.affected).forEach(m => {
    const shapes = document.querySelectorAll(`.muscle-shape[data-muscle="${m.muscle_key}"]`);
    shapes.forEach(shape => {
      shape.style.fill = m.status_color_after;
      shape.style.filter = `drop-shadow(0 0 10px ${m.status_color_after})`;
    });
  });
}

async function handlePreviewClick() {
  const sets = collectPlannerSets();
  if (sets.length === 0) {
    showPlannerFeedback('Add at least one exercise to preview.', true);
    return;
  }

  const targetDate = document.getElementById('planner-date').value || null;

  try {
    const data = await postPreview({ target_date: targetDate, sets });
    renderPlannerResults(data.muscles, data.target_date);
    applyPreviewColors(data.muscles);
    document.getElementById('clear-preview-btn').style.display = 'inline-block';
    showPlannerFeedback('', false);
  } catch (err) {
    showPlannerFeedback(err.message, true);
  }
}

async function handleClearPreview() {
  document.getElementById('clear-preview-btn').style.display = 'none';
  document.getElementById('planner-results').innerHTML = '';
  showPlannerFeedback('', false);
  await refreshMuscles(); // restores the hologram + list to the real current state
}

/* ---------- LOG WORKOUT FORM: SET ROWS ---------- */

function addSetRow() {
  const container = document.getElementById('sets-container');
  const row = document.createElement('div');
  row.className = 'set-row';
  const unit = getWeightUnitLabel();
  row.innerHTML = `
    <button type="button" class="exercise-picker-btn">Choose Exercise</button>
    <input type="hidden" class="set-exercise" value="">
    <input type="number" class="set-reps" placeholder="Reps" min="1" required>
    <input type="number" class="set-weight" placeholder="${unit}" min="0" step="0.5">
    <button type="button" class="weight-help-btn" title="Weight logging help">?</button>
    <input type="number" class="set-rpe" placeholder="RPE" min="1" max="10" step="0.5">
    <button type="button" class="remove-set-btn" title="Remove set">×</button>
  `;
  row.querySelector('.exercise-picker-btn').addEventListener('click', () => openExercisePicker(row));
  row.querySelector('.weight-help-btn').addEventListener('click', (event) => toggleWeightHelp(event, row));
  row.querySelector('.remove-set-btn').addEventListener('click', () => row.remove());
  container.appendChild(row);
}

function collectSetsFromForm() {
  const rows = document.querySelectorAll('.set-row');
  const sets = [];

  rows.forEach(row => {
    const exerciseId = row.querySelector('.set-exercise').value;
    const reps = row.querySelector('.set-reps').value;
    const weightRaw = row.querySelector('.set-weight').value;
    const rpe = row.querySelector('.set-rpe').value;

    if (exerciseId && reps) {
      const weightInKg = weightRaw ? displayWeightToKg(parseFloat(weightRaw)) : 0;
      sets.push({
        exercise_id: parseInt(exerciseId, 10),
        reps: parseInt(reps, 10),
        weight_kg: weightInKg,
        rpe: rpe ? parseFloat(rpe) : null,
      });
    }
  });

  return sets;
}

/* ---------- REFRESH HELPERS ---------- */

async function refreshMuscles() {
  const data = await fetchMuscles();
  state.muscles = data.muscles;
  renderMuscleList(state.muscles);
  applyHologramColors(state.muscles);

  const updated = document.getElementById('last-updated');
  updated.textContent = 'Last scan: ' + new Date(data.generated_at).toLocaleTimeString();
}

async function refreshHistory() {
  const data = await fetchHistory();
  renderHistory(data.workouts);
}

async function loadExercises() {
  const data = await fetchExercises();
  state.exercises = data.exercises;
}

/* ---------- PROFILE MODAL ---------- */

function updateProfileUnitLabels() {
  const isImperial = state.unitSystem === 'imperial';
  document.getElementById('label-profile-weight').innerHTML = isImperial
    ? 'Body Weight (lbs) *'
    : 'Body Weight (kg) *';
  document.getElementById('label-profile-height').innerHTML = isImperial
    ? 'Height (in) <span class="profile-optional">(optional)</span>'
    : 'Height (cm) <span class="profile-optional">(optional)</span>';

  // Atualiza também os placeholders dos inputs de peso existentes no formulário de treino
  document.querySelectorAll('.set-row .set-weight').forEach(input => {
    input.placeholder = getWeightUnitLabel();
  });
}

function updateProfileIndicator() {
  const indicator = document.getElementById('profile-indicator');
  const isComplete = Boolean(state.profile && state.profile.body_weight_kg > 0);
  indicator.style.display = isComplete ? 'none' : 'inline-block';
}

function openProfileModal() {
  const p = state.profile;
  document.getElementById('profile-units').value = state.unitSystem;
  updateProfileUnitLabels();

  document.getElementById('profile-sex').value = p ? p.sex : 'unspecified';

  const displayedWeight = p && p.body_weight_kg ? kgToDisplayWeight(parseFloat(p.body_weight_kg)) : '';
  const displayedHeight = p && p.height_cm ? cmToDisplayHeight(parseFloat(p.height_cm)) : '';

  document.getElementById('profile-weight').value = displayedWeight;
  document.getElementById('profile-height').value = displayedHeight;
  document.getElementById('profile-activity').value = p ? p.activity_level : 'moderately_active';

  const feedback = document.getElementById('profile-feedback');
  feedback.textContent = '';
  feedback.className = 'log-feedback';

  document.getElementById('profile-modal').style.display = 'flex';
}

function closeProfileModal() {
  document.getElementById('profile-modal').style.display = 'none';
}

async function handleProfileSubmit(event) {
  event.preventDefault();
  const feedback = document.getElementById('profile-feedback');
  const unitSelect = document.getElementById('profile-units').value;
  const sex = document.getElementById('profile-sex').value;
  const weightInput = parseFloat(document.getElementById('profile-weight').value);
  const heightRaw = document.getElementById('profile-height').value;
  const activity = document.getElementById('profile-activity').value;

  // Atualizar preferência de unidades
  state.unitSystem = unitSelect;
  localStorage.setItem('recovery_unit_system', unitSelect);
  updateProfileUnitLabels();

  // Converter para métrico (kg / cm) antes de guardar no backend
  const weightKg = displayWeightToKg(weightInput);
  const heightCm = heightRaw ? displayHeightToCm(parseFloat(heightRaw)) : null;

  if (!weightKg || weightKg <= 0 || weightKg > 400) {
    feedback.textContent = `Please enter a valid body weight (${getWeightUnitLabel()}).`;
    feedback.className = 'log-feedback error';
    return;
  }

  try {
    const res = await saveProfileApi({
      sex: sex,
      body_weight_kg: weightKg,
      height_cm: heightCm,
      activity_level: activity,
    });
    state.profile = res.profile;
    updateProfileIndicator();
    updateHologramVisibility();
    feedback.textContent = 'Profile saved.';
    feedback.className = 'log-feedback success';

    await refreshMuscles();
    await refreshHistory(); // Atualiza os rótulos de peso no histórico para a unidade selecionada
    setTimeout(closeProfileModal, 600);
  } catch (err) {
    feedback.textContent = err.message;
    feedback.className = 'log-feedback error';
  }
}

async function loadProfile() {
  try {
    const data = await fetchProfile();
    state.profile = data.profile;
    updateProfileIndicator();
    updateHologramVisibility();
  } catch (err) {
    console.error(err);
  }
}

/* ---------- EVENT HANDLERS ---------- */

function showFeedback(message, isError) {
  const el = document.getElementById('log-feedback');
  el.textContent = message;
  el.className = 'log-feedback ' + (isError ? 'error' : 'success');
}

async function handleWorkoutSubmit(event) {
  event.preventDefault();

  const sets = collectSetsFromForm();
  if (sets.length === 0) {
    showFeedback('Add at least one valid set before saving.', true);
    return;
  }

  const label = document.getElementById('workout-label').value.trim() || null;

  try {
    if (state.editingWorkoutId) {
      const result = await updateWorkout(state.editingWorkoutId, { label, sets });
      showFeedback(`Updated — ${result.sets_logged} sets saved.`, false);
      exitEditMode();
    } else {
      const result = await postWorkout({ label, sets });
      showFeedback(`Saved — ${result.sets_logged} sets logged.`, false);
      document.getElementById('workout-form').reset();
      document.getElementById('sets-container').innerHTML = '';
      addSetRow();
    }

    await refreshMuscles();
    await refreshHistory();
  } catch (err) {
    showFeedback(err.message, true);
  }
}

function updateHologramVisibility() {
  const isFemale = state.profile && state.profile.sex === 'female';
  const isFront = state.hologramView === 'front';

  const maleFront = document.getElementById('body-front');
  const maleBack = document.getElementById('body-back');
  const femaleFront = document.getElementById('body-front-female');
  const femaleBack = document.getElementById('body-back-female');

  maleFront.style.display = (!isFemale && isFront) ? '' : 'none';
  maleBack.style.display = (!isFemale && !isFront) ? '' : 'none';
  femaleFront.style.display = (isFemale && isFront) ? '' : 'none';
  femaleBack.style.display = (isFemale && !isFront) ? '' : 'none';

  const btn = document.getElementById('toggle-view');
  if (btn) {
    btn.textContent = isFront ? 'Show Back' : 'Show Front';
  }
}

function handleViewToggle() {
  state.hologramView = state.hologramView === 'front' ? 'back' : 'front';
  updateHologramVisibility();
}

function bindEvents() {
  document.getElementById('toggle-view').addEventListener('click', handleViewToggle);
  document.getElementById('add-set-btn').addEventListener('click', addSetRow);
  document.getElementById('workout-form').addEventListener('submit', handleWorkoutSubmit);
  document.getElementById('cancel-edit-btn').addEventListener('click', exitEditMode);

  document.getElementById('profile-btn').addEventListener('click', openProfileModal);
  document.getElementById('close-profile-btn').addEventListener('click', closeProfileModal);
  document.getElementById('cancel-profile-btn').addEventListener('click', closeProfileModal);
  document.getElementById('profile-form').addEventListener('submit', handleProfileSubmit);
  document.getElementById('profile-modal').addEventListener('click', (event) => {
    if (event.target.id === 'profile-modal') closeProfileModal();
  });

  document.getElementById('close-picker-btn').addEventListener('click', closeExercisePicker);
  document.getElementById('exercise-picker-modal').addEventListener('click', (event) => {
    if (event.target.id === 'exercise-picker-modal') closeExercisePicker();
  });

  document.getElementById('planner-add-set-btn').addEventListener('click', addPlannerSetRow);
  document.getElementById('preview-btn').addEventListener('click', handlePreviewClick);
  document.getElementById('clear-preview-btn').addEventListener('click', handleClearPreview);

  document.addEventListener('click', (event) => {
    const tooltip = document.getElementById('weight-help-tooltip');
    if (tooltip.style.display !== 'block') return;
    if (event.target.classList.contains('weight-help-btn')) return;
    if (tooltip.contains(event.target)) return;
    closeWeightHelp();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeWeightHelp();
      closeExercisePicker();
      closeProfileModal();
    }
  });

  document.getElementById('profile-units').addEventListener('change', (e) => {
    const newUnit = e.target.value;
    const weightField = document.getElementById('profile-weight');
    const heightField = document.getElementById('profile-height');

    const currentWeight = parseFloat(weightField.value);
    const currentHeight = parseFloat(heightField.value);

    // Converte os valores que já estão digitados no modal
    if (newUnit === 'imperial' && state.unitSystem === 'metric') {
      if (currentWeight) weightField.value = Math.round(currentWeight * LBS_PER_KG * 10) / 10;
      if (currentHeight) heightField.value = Math.round(currentHeight * IN_PER_CM * 10) / 10;
    } else if (newUnit === 'metric' && state.unitSystem === 'imperial') {
      if (currentWeight) weightField.value = Math.round((currentWeight / LBS_PER_KG) * 10) / 10;
      if (currentHeight) heightField.value = Math.round((currentHeight / IN_PER_CM) * 10) / 10;
    }

    state.unitSystem = newUnit;
    updateProfileUnitLabels();
  });
}

/* ---------- CLOCK ---------- */

function startClock() {
  const clock = document.getElementById('clock');
  const tick = () => { clock.textContent = new Date().toLocaleTimeString(); };
  tick();
  setInterval(tick, 1000);
}

/* ---------- BOOT SEQUENCE ---------- */

const BOOT_MESSAGES = [
  'INITIALIZING RECOVERY INTERFACE...',
  'ESTABLISHING DATABASE LINK...',
  'LOADING EXERCISE LIBRARY...',
  'SCANNING MUSCLE TELEMETRY...',
  'CALIBRATING FATIGUE MODELS...',
  'SYNCING SESSION HISTORY...',
  'SYSTEMS NOMINAL.',
];

function wait(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function typeBootLine(text) {
  return new Promise(resolve => {
    const log = document.getElementById('boot-log');
    const li = document.createElement('li');
    li.className = 'boot-log-line';
    log.appendChild(li);

    let i = 0;
    const interval = setInterval(() => {
      li.textContent += text[i];
      i++;
      if (i >= text.length) {
        clearInterval(interval);
        li.classList.add('done');
        resolve();
      }
    }, 14);
  });
}

function setBootProgress(percent) {
  const clamped = Math.min(100, Math.max(0, percent));
  document.getElementById('boot-percent').textContent = Math.round(clamped) + '%';
  document.getElementById('boot-bar-fill').style.width = clamped + '%';

  const ring = document.getElementById('boot-ring-progress');
  const circumference = 2 * Math.PI * 90;
  ring.style.strokeDashoffset = circumference * (1 - clamped / 100);
}

async function runBootAnimation() {
  const ring = document.getElementById('boot-ring-progress');
  const circumference = 2 * Math.PI * 90;
  ring.style.strokeDasharray = circumference;
  ring.style.strokeDashoffset = circumference;
  setBootProgress(0);

  for (let i = 0; i < BOOT_MESSAGES.length; i++) {
    await typeBootLine(BOOT_MESSAGES[i]);
    setBootProgress(((i + 1) / BOOT_MESSAGES.length) * 100);
    await wait(120);
  }
}

async function completeBoot() {
  const bootScreen = document.getElementById('boot-screen');
  const hudFrame = document.getElementById('hud-frame');

  bootScreen.classList.add('boot-complete');
  hudFrame.classList.remove('pre-reveal');
  document.body.classList.remove('booting');

  await wait(700);
  bootScreen.style.display = 'none';
}

/* ---------- INIT ---------- */

async function init() {
  startClock();
  bindEvents();

  const bootAnimation = runBootAnimation();

  const dataLoad = (async () => {
    try {
      await loadProfile();
      await loadExercises();
      addSetRow();

      const plannerDateInput = document.getElementById('planner-date');
      const today = new Date().toISOString().slice(0, 10);
      plannerDateInput.value = today;
      plannerDateInput.min = today;
      addPlannerSetRow();

      await refreshMuscles();
      await refreshHistory();
    } catch (err) {
      console.error (err);
      showFeedback('Could not reach the backend — check XAMPP is running.', true);
    }
  })();

  await Promise.all([bootAnimation, dataLoad]);
  await completeBoot();
}

document.addEventListener('DOMContentLoaded', init);