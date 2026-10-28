/* ============================================================
   RECOVERY INTERFACE — Workout Logging & History
   ============================================================ */

import { state, getWeightUnitLabel, kgToDisplayWeight, displayWeightToKg } from './state.js';
import { fetchHistory, postWorkout, updateWorkout, fetchWorkoutById, deleteWorkoutApi } from './api.js';
import { openExercisePicker, toggleWeightHelp } from './picker.js';
import { refreshMuscles } from './hologram.js';
import { refreshAnalytics } from './analytics.js';
import { switchConsoleTab } from './main.js';

export function addSetRow() {
  const container = document.getElementById('sets-container');
  if (!container) return;
  const row = document.createElement('div');
  row.className = 'set-row';
  const unit = getWeightUnitLabel();
  row.innerHTML = `
    <button type="button" class="exercise-picker-btn">Choose Exercise</button>
    <input type="hidden" class="set-exercise" value="">
    <label class="set-field" data-field="reps">
      <span class="set-field-label">Reps</span>
      <input type="number" class="set-reps" placeholder="Reps" min="1" inputmode="numeric" required>
    </label>
    <label class="set-field" data-field="weight">
      <span class="set-field-label">${unit}</span>
      <input type="number" class="set-weight" placeholder="${unit}" min="0" step="0.5" inputmode="decimal">
    </label>
    <button type="button" class="weight-help-btn" title="Weight logging help">?</button>
    <label class="set-field" data-field="rpe">
      <span class="set-field-label">RPE</span>
      <input type="number" class="set-rpe" placeholder="RPE" min="1" max="10" step="0.5" inputmode="decimal">
    </label>
    <button type="button" class="remove-set-btn" title="Remove set">×</button>
  `;
  row.querySelector('.exercise-picker-btn').addEventListener('click', () => openExercisePicker(row));
  row.querySelector('.weight-help-btn').addEventListener('click', (event) => toggleWeightHelp(event, row));
  row.querySelector('.remove-set-btn').addEventListener('click', () => row.remove());
  container.appendChild(row);
}

export function collectSetsFromForm() {
  const rows = document.querySelectorAll('#sets-container .set-row');
  const sets = [];

  rows.forEach(row => {
    const exerciseEl = row.querySelector('.set-exercise');
    const repsEl = row.querySelector('.set-reps');
    const weightEl = row.querySelector('.set-weight');
    const rpeEl = row.querySelector('.set-rpe');

    if (!exerciseEl || !repsEl) return;

    const exerciseId = exerciseEl.value;
    const reps = repsEl.value;
    const weightRaw = weightEl ? weightEl.value : '';
    const rpe = rpeEl ? rpeEl.value : '';

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

export function showFeedback(message, isError) {
  const el = document.getElementById('log-feedback');
  if (!el) return;
  el.textContent = message;
  el.className = 'log-feedback ' + (isError ? 'error' : 'success');
}

export function renderHistory(workouts) {
  const list = document.getElementById('history-list');
  if (!list) return;
  list.innerHTML = '';

  if (!workouts || workouts.length === 0) {
    const emptyMsg = document.createElement('p');
    emptyMsg.className = 'hologram-footnote';
    emptyMsg.textContent = 'No sessions logged yet.';
    list.appendChild(emptyMsg);
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

        // workout_date arrives as UTC ISO ("...Z"); the browser converts it to local time
    const date = new Date(w.workout_date);
    const dateStr = date.toLocaleDateString(undefined, { 
      month: 'short', 
      day: 'numeric', 
      hour: '2-digit', 
      minute: '2-digit' 
    });

    const li = document.createElement('li');
    li.className = 'history-card';

    // 1. Título do Treino (Atribuição segura imune a XSS)
    const titleEl = document.createElement('div');
    titleEl.className = 'history-card-title';
    titleEl.textContent = w.label || 'Workout';
    li.appendChild(titleEl);

    // 2. Data da Sessão
    const dateEl = document.createElement('div');
    dateEl.className = 'history-card-date';
    dateEl.textContent = dateStr;
    li.appendChild(dateEl);

    // 3. Resumo de Séries (Construção segura de nós DOM)
    const setsEl = document.createElement('div');
    setsEl.className = 'history-card-sets';
    Object.entries(bySets).forEach(([name, lines]) => {
      const row = document.createElement('div');
      const strong = document.createElement('strong');
      strong.textContent = name + ': ';
      row.appendChild(strong);
      row.appendChild(document.createTextNode(lines.join(', ')));
      setsEl.appendChild(row);
    });
    li.appendChild(setsEl);

    // 4. Ações
    const actionsEl = document.createElement('div');
    actionsEl.className = 'history-card-actions';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'history-action-btn edit-workout-btn';
    editBtn.textContent = 'Edit';
    editBtn.addEventListener('click', () => handleEditWorkout(w.id));

    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'history-action-btn delete-workout-btn';
    deleteBtn.textContent = 'Delete';
    deleteBtn.addEventListener('click', () => handleDeleteWorkout(w.id));

    actionsEl.appendChild(editBtn);
    actionsEl.appendChild(deleteBtn);
    li.appendChild(actionsEl);

    list.appendChild(li);
  });
}

export async function refreshHistory() {
  const data = await fetchHistory();
  renderHistory(data.workouts);
}

export function enterEditMode(workout) {
  state.editingWorkoutId = workout.id;

  document.getElementById('log-panel-label').textContent = 'Edit session';
  document.getElementById('save-workout-btn').textContent = 'Update workout';
  document.getElementById('cancel-edit-btn').style.display = 'inline-flex';
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

  // Comuta automaticamente para o ecrã de Workout
  switchConsoleTab('tab-workout');
}

export function exitEditMode() {
  state.editingWorkoutId = null;
  document.getElementById('log-panel-label').textContent = 'Log a session';
  document.getElementById('save-workout-btn').textContent = 'Save workout';
  document.getElementById('cancel-edit-btn').style.display = 'none';
  document.getElementById('workout-form').reset();
  document.getElementById('sets-container').innerHTML = '';
  addSetRow();
}

export async function handleEditWorkout(workoutId) {
  try {
    const workout = await fetchWorkoutById(workoutId);
    enterEditMode(workout);
  } catch (err) {
    console.error(err);
    alert(err.message);
  }
}

export async function handleDeleteWorkout(workoutId) {
  if (!confirm('Delete this workout? This cannot be undone.')) return;

  try {
    await deleteWorkoutApi(workoutId);
    if (state.editingWorkoutId === workoutId) {
      exitEditMode();
    }
    await refreshHistory();
    await refreshMuscles();
    await refreshAnalytics();
  } catch (err) {
    console.error(err);
    alert(err.message);
  }
}

export async function handleWorkoutSubmit(event) {
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
    await refreshAnalytics();
  } catch (err) {
    showFeedback(err.message, true);
  }
}