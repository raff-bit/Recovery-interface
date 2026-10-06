/* ============================================================
   RECOVERY INTERFACE — Exercise Picker & Weight Tooltip
   ============================================================ */

import { state, MUSCLE_GROUPS } from './state.js';

export function getExercisePrimaryRegion(exercise) {
  if (!exercise.muscles || exercise.muscles.length === 0) return 'other';

  const primaries = exercise.muscles.filter(m => m.role === 'primary');
  const pool = primaries.length > 0 ? primaries : exercise.muscles;

  const top = pool.reduce((best, m) =>
    m.activation_weight > best.activation_weight ? m : best
  , pool[0]);

  return top.body_region;
}

export function openExercisePicker(rowElement) {
  state.activePickerRow = rowElement;
  state.activePickerRegion = 'all';
  renderPickerTabs();
  renderPickerList();
  document.getElementById('exercise-picker-modal').style.display = 'flex';
}

export function closeExercisePicker() {
  document.getElementById('exercise-picker-modal').style.display = 'none';
  state.activePickerRow = null;
}

export function renderPickerTabs() {
  const container = document.getElementById('picker-tabs');
  if (!container) return;
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

export function renderPickerList() {
  const list = document.getElementById('picker-list');
  if (!list) return;
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

export function selectExerciseForRow(exercise) {
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

const EQUIPMENT_HELP = {
  'Barbell':    'Enter the TOTAL weight lifted, including the bar itself (e.g. bar + all plates).',
  'Dumbbell':   'Enter the weight of ONE dumbbell — not the combined weight of both.',
  'Machine':    "Enter the weight shown on the machine's stack or pin setting.",
  'Cable':      'Enter the weight set on the cable stack.',
  'Bodyweight': 'Leave at 0 for bodyweight only, or enter any added weight (vest, belt, plate).',
};

export function getWeightHelpText(row) {
  const hiddenInput = row.querySelector('.set-exercise');
  const equipment = hiddenInput ? hiddenInput.dataset.equipment : '';

  if (!equipment) {
    return 'Choose an exercise first — this tip adjusts to how that equipment is usually logged.';
  }
  return EQUIPMENT_HELP[equipment] || 'Enter the weight used for this exercise.';
}

export function toggleWeightHelp(event, row) {
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

export function closeWeightHelp() {
  const tooltip = document.getElementById('weight-help-tooltip');
  if (tooltip) {
    tooltip.style.display = 'none';
    tooltip._openBtn = null;
  }
}