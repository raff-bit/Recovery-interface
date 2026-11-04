/* ============================================================
   RECOVERY INTERFACE — Routines (saved session templates)
   ------------------------------------------------------------
   A routine is a list of exercises with planned sets/reps/weight/RPE.
     - "Load a routine…" (Log + Plan) fills the rows from a routine.
     - "Save as routine" turns what's on screen into a routine.
   Built-in templates (Push/Pull/Leg day) are read-only for everyone.
   All names are user text → only ever inserted with textContent.
   ============================================================ */

import { state, kgToDisplayWeight, displayWeightToKg } from './state.js';
import { fetchRoutines, saveRoutineApi, deleteRoutineApi } from './api.js';
import { addSetRow, regroupSetRows } from './workout.js';
import { addPlannerSetRow } from './planner.js';

const MAX_SETS_PER_ITEM = 20; // mirrors RoutineValidator::MAX_SETS_PER_ITEM
const MAX_ITEMS = 30;         // mirrors RoutineValidator::MAX_ITEMS

/* ---------------- loading ---------------- */

export async function loadRoutines() {
  try {
    const data = await fetchRoutines();
    state.routines = data.routines || [];
  } catch (err) {
    console.error(err);
    state.routines = [];
  }
  renderRoutineSelects();
  updateRoutineButtons();
}

/** Fills both "Load a routine…" menus: your routines first, then the templates. */
export function renderRoutineSelects() {
  for (const id of ['log-routine-select', 'planner-routine-select']) {
    const select = document.getElementById(id);
    if (!select) continue;

    select.replaceChildren();
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Load a routine…';
    select.appendChild(placeholder);

    const groups = [
      ['Your routines', state.routines.filter(r => !r.is_template)],
      ['Templates', state.routines.filter(r => r.is_template)],
    ];
    for (const [label, routines] of groups) {
      if (routines.length === 0) continue;
      const group = document.createElement('optgroup');
      group.label = label;
      routines.forEach(r => {
        const option = document.createElement('option');
        option.value = String(r.id);
        option.textContent = r.name;
        group.appendChild(option);
      });
      select.appendChild(group);
    }
    select.value = '';
  }
}

/** Saving routines needs an account; guests can still load the templates. */
export function updateRoutineButtons() {
  const signedIn = Boolean(state.user?.authenticated);
  for (const id of ['log-save-routine-btn', 'planner-save-routine-btn']) {
    const btn = document.getElementById(id);
    if (btn) btn.style.display = signedIn ? '' : 'none';
  }
}

/* ---------------- applying a routine ---------------- */

function fillExercise(row, exerciseId, exerciseName) {
  const exercise = state.exercises.find(ex => ex.id === exerciseId);
  const hidden = row.querySelector('.set-exercise');
  const btn = row.querySelector('.exercise-picker-btn');
  hidden.value = String(exerciseId);
  hidden.dataset.equipment = exercise?.equipment || '';
  hidden.dataset.bodyweight = exercise?.is_bodyweight ? '1' : '0';
  btn.textContent = exercise?.name || exerciseName;
  btn.classList.add('selected');
}

function hasEnteredRows(containerId) {
  return Array.from(document.querySelectorAll(`#${containerId} .set-exercise`)).some(el => el.value !== '');
}

function findRoutine(id) {
  return state.routines.find(r => String(r.id) === String(id)) || null;
}

/** Log: every planned set becomes its own row (4×8 → four rows), ready to tweak and save. */
export function applyRoutineToLog(routine) {
  if (hasEnteredRows('sets-container') && !confirm(`Replace the sets you've entered with "${routine.name}"?`)) {
    return false;
  }

  const container = document.getElementById('sets-container');
  container.replaceChildren();

  routine.items.forEach(item => {
    for (let i = 0; i < item.sets; i++) {
      const row = addSetRow();
      fillExercise(row, item.exercise_id, item.exercise_name);
      row.querySelector('.set-reps').value = item.reps;
      row.querySelector('.set-weight').value = item.weight_kg !== null ? kgToDisplayWeight(item.weight_kg) : '';
      row.querySelector('.set-rpe').value = item.rpe ?? '';
    }
  });
  regroupSetRows();
  const label = document.getElementById('workout-label');
  if (label && label.value.trim() === '') label.value = routine.name;
  return true;
}

/** Plan: one row per exercise, with its "Sets" count. */
export function applyRoutineToPlanner(routine) {
  if (hasEnteredRows('planner-sets-container') && !confirm(`Replace the planned exercises with "${routine.name}"?`)) {
    return false;
  }

  const container = document.getElementById('planner-sets-container');
  container.replaceChildren();

  routine.items.forEach(item => {
    const row = addPlannerSetRow();
    fillExercise(row, item.exercise_id, item.exercise_name);
    row.querySelector('.set-count').value = item.sets;
    row.querySelector('.planner-set-reps').value = item.reps;
    row.querySelector('.planner-set-weight').value = item.weight_kg !== null ? kgToDisplayWeight(item.weight_kg) : '';
    if (item.rpe !== null) row.querySelector('.set-rpe').value = item.rpe; // else keep the planner's default
  });
  return true;
}

function handleRoutineSelect(event, target) {
  const select = event.target;
  const routine = findRoutine(select.value);
  select.value = ''; // the menu is an action, not a setting: reset it
  if (!routine) return;

  const applied = target === 'log' ? applyRoutineToLog(routine) : applyRoutineToPlanner(routine);
  const feedbackId = target === 'log' ? 'log-feedback' : 'planner-feedback';
  const feedback = document.getElementById(feedbackId);
  if (applied && feedback) {
    feedback.textContent = `Loaded "${routine.name}". Adjust anything, then ${target === 'log' ? 'save the workout' : 'preview'}.`;
    feedback.className = 'log-feedback success';
  }
}

/* ---------------- turning the screen into a routine ---------------- */

const toKgOrNull = (raw) => (raw === '' ? null : Math.round(displayWeightToKg(parseFloat(raw)) * 100) / 100);
const toFloatOrNull = (raw) => (raw === '' ? null : parseFloat(raw));

/** Log rows → items. Consecutive identical sets collapse into one item: 3 rows of 8 @ 60 → 3×8 @ 60. */
export function itemsFromLog() {
  const items = [];
  document.querySelectorAll('#sets-container .set-row').forEach(row => {
    const exerciseId = row.querySelector('.set-exercise').value;
    const reps = row.querySelector('.set-reps').value;
    if (!exerciseId || !reps) return;

    const item = {
      exercise_id: parseInt(exerciseId, 10),
      sets: 1,
      reps: parseInt(reps, 10),
      weight_kg: toKgOrNull(row.querySelector('.set-weight').value),
      rpe: toFloatOrNull(row.querySelector('.set-rpe').value),
    };

    const last = items[items.length - 1];
    const same = last
      && last.exercise_id === item.exercise_id && last.reps === item.reps
      && last.weight_kg === item.weight_kg && last.rpe === item.rpe
      && last.sets < MAX_SETS_PER_ITEM;
    if (same) {
      last.sets += 1;
    } else {
      items.push(item);
    }
  });
  return items;
}

/** Planner rows map 1:1 to items. */
export function itemsFromPlanner() {
  const items = [];
  document.querySelectorAll('#planner-sets-container .planner-set-row').forEach(row => {
    const exerciseId = row.querySelector('.set-exercise').value;
    if (!exerciseId) return;
    const count = parseInt(row.querySelector('.set-count').value, 10) || 1;
    const reps = parseInt(row.querySelector('.planner-set-reps').value, 10) || 10;
    items.push({
      exercise_id: parseInt(exerciseId, 10),
      sets: Math.min(Math.max(count, 1), MAX_SETS_PER_ITEM),
      reps,
      weight_kg: toKgOrNull(row.querySelector('.planner-set-weight').value),
      rpe: toFloatOrNull(row.querySelector('.set-rpe').value),
    });
  });
  return items;
}

/* ---------------- the Routines modal ---------------- */

let pendingItems = [];

function setRoutineFeedback(message, isError) {
  const el = document.getElementById('routine-feedback');
  el.textContent = message;
  el.className = 'log-feedback' + (message ? (isError ? ' error' : ' success') : '');
}

function renderManageList() {
  const list = document.getElementById('routine-manage-list');
  list.replaceChildren();
  const own = state.routines.filter(r => !r.is_template);

  if (own.length === 0) {
    const empty = document.createElement('li');
    empty.className = 'hologram-footnote';
    empty.textContent = 'No routines yet. The templates are always available in "Load a routine…".';
    list.appendChild(empty);
    return;
  }

  own.forEach(r => {
    const li = document.createElement('li');
    li.className = 'routine-manage-item';

    const text = document.createElement('div');
    const name = document.createElement('div');
    name.className = 'routine-manage-name';
    name.textContent = r.name;
    const meta = document.createElement('div');
    meta.className = 'routine-manage-meta';
    const totalSets = r.items.reduce((sum, i) => sum + i.sets, 0);
    meta.textContent = `${r.items.length} exercise${r.items.length === 1 ? '' : 's'} · ${totalSets} sets`;
    text.append(name, meta);

    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'history-action-btn delete-workout-btn';
    del.textContent = 'Delete';
    del.setAttribute('aria-label', `Delete routine ${r.name}`);
    del.addEventListener('click', () => handleDeleteRoutine(r));

    li.append(text, del);
    list.appendChild(li);
  });
}

export function openRoutineModal(source) {
  pendingItems = source === 'planner' ? itemsFromPlanner() : itemsFromLog();

  const summary = document.getElementById('routine-summary');
  const nameInput = document.getElementById('routine-name');
  const saveBtn = document.getElementById('save-routine-btn');
  setRoutineFeedback('', false);

  if (pendingItems.length === 0) {
    summary.textContent = source === 'planner'
      ? 'Choose at least one exercise in the planner first, then save it here.'
      : 'Fill in at least one set (exercise + reps) first, then save it here.';
    saveBtn.disabled = true;
  } else {
    const totalSets = pendingItems.reduce((sum, i) => sum + i.sets, 0);
    summary.textContent = `${pendingItems.length} exercise${pendingItems.length === 1 ? '' : 's'}, ${totalSets} sets, with their reps, weight and RPE.`;
    saveBtn.disabled = false;
  }

  const sessionLabel = source === 'log' ? document.getElementById('workout-label').value.trim() : '';
  nameInput.value = sessionLabel;
  renderManageList();

  document.getElementById('routine-modal').style.display = 'flex';
  if (!saveBtn.disabled && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    nameInput.focus();
  }
}

export function closeRoutineModal() {
  const modal = document.getElementById('routine-modal');
  if (modal) modal.style.display = 'none';
}

export async function handleRoutineSubmit(event) {
  event.preventDefault();
  const name = document.getElementById('routine-name').value.trim();

  if (pendingItems.length === 0) return;
  if (name === '') {
    setRoutineFeedback('Give the routine a name.', true);
    return;
  }
  if (pendingItems.length > MAX_ITEMS) {
    setRoutineFeedback(`A routine can have at most ${MAX_ITEMS} exercises.`, true);
    return;
  }

  // Same name as one of YOUR routines → offer to replace it (names are case-insensitive in the DB)
  const existing = state.routines.find(r => !r.is_template && r.name.toLowerCase() === name.toLowerCase());
  if (existing && !confirm(`You already have "${existing.name}". Replace it?`)) return;

  const payload = { name, items: pendingItems };
  if (existing) payload.routine_id = existing.id;

  try {
    await saveRoutineApi(payload);
    await loadRoutines();
    closeRoutineModal();
    // Confirm in whichever panel (Log or Plan) the user is looking at
    for (const id of ['log-feedback', 'planner-feedback']) {
      const el = document.getElementById(id);
      if (el && el.closest('.hud-tab-panel')?.classList.contains('active')) {
        el.textContent = `Routine "${name}" saved.`;
        el.className = 'log-feedback success';
      }
    }
  } catch (err) {
    setRoutineFeedback(err.message, true);
  }
}

async function handleDeleteRoutine(routine) {
  if (!confirm(`Delete the routine "${routine.name}"? Logged workouts are not affected.`)) return;
  try {
    await deleteRoutineApi(routine.id);
    await loadRoutines();
    renderManageList();
    setRoutineFeedback(`Deleted "${routine.name}".`, false);
  } catch (err) {
    setRoutineFeedback(err.message, true);
  }
}

/* ---------------- wiring ---------------- */

export function initRoutines() {
  document.getElementById('log-routine-select')?.addEventListener('change', (e) => handleRoutineSelect(e, 'log'));
  document.getElementById('planner-routine-select')?.addEventListener('change', (e) => handleRoutineSelect(e, 'planner'));
  document.getElementById('log-save-routine-btn')?.addEventListener('click', () => openRoutineModal('log'));
  document.getElementById('planner-save-routine-btn')?.addEventListener('click', () => openRoutineModal('planner'));

  document.getElementById('routine-form').addEventListener('submit', handleRoutineSubmit);
  document.getElementById('close-routine-btn').addEventListener('click', closeRoutineModal);
  document.getElementById('cancel-routine-btn').addEventListener('click', closeRoutineModal);
  document.getElementById('routine-modal').addEventListener('click', (event) => {
    if (event.target.id === 'routine-modal') closeRoutineModal();
  });

  renderRoutineSelects();
  updateRoutineButtons();
}
