/* ============================================================
   RECOVERY INTERFACE — Exercise Picker & Weight Tooltip
   ============================================================ */

import { state, MUSCLE_GROUPS } from './state.js';
import { regroupSetRows } from './workout.js';  

export function getExercisePrimaryRegion(exercise) {
  if (!exercise.muscles || exercise.muscles.length === 0) return 'other';

  const primaries = exercise.muscles.filter(m => m.role === 'primary');
  const pool = primaries.length > 0 ? primaries : exercise.muscles;

  const top = pool.reduce((best, m) =>
    m.activation_weight > best.activation_weight ? m : best
  , pool[0]);

  return top.body_region;
}

let searchQuery = '';

/** Lower-case and strip accents, so search ignores both case and accents. */
function normalize(text) {
  return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

/** Wire the search box once, at startup (called from main.js). */
export function initExercisePicker() {
  const search = document.getElementById('picker-search');
  if (!search) return;

  search.addEventListener('input', () => {
    searchQuery = search.value.trim();
    // Typing searches EVERY group: switch the filter back to "All"
    if (searchQuery && state.activePickerRegion !== 'all') {
      state.activePickerRegion = 'all';
      renderPickerTabs();
    }
    renderPickerList();
  });

  // Enter picks the first match: type "squ" + Enter and you're done
  search.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const first = document.querySelector('#picker-list .picker-item');
    if (first) first.click();
  });
}

export function openExercisePicker(rowElement) {
  state.activePickerRow = rowElement;
  state.activePickerRegion = 'all';
  searchQuery = '';
  const search = document.getElementById('picker-search');
  if (search) search.value = '';

  renderPickerTabs();
  renderPickerList();
  document.getElementById('exercise-picker-modal').style.display = 'flex';

  // Focus the search on desktop only — on phones it would pop the keyboard over the list
  if (search && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    search.focus();
  }
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
    const active = state.activePickerRegion === group.key;
    btn.className = 'picker-tab' + (active ? ' active' : '');
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    btn.textContent = group.label;
    btn.addEventListener('click', () => {
      // Filters browse, search finds: picking a group clears the search
      searchQuery = '';
      const search = document.getElementById('picker-search');
      if (search) search.value = '';
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
  const query = normalize(searchQuery);

  const filtered = state.exercises.filter(ex =>
    (region === 'all' || getExercisePrimaryRegion(ex) === region) &&
    (query === '' || normalize(`${ex.name} ${ex.equipment || ''}`).includes(query))
  );

  if (filtered.length === 0) {
    const empty = document.createElement('p');
    empty.className = 'hologram-footnote';
    empty.textContent = query ? `No exercises match "${searchQuery}".` : 'No exercises in this group.';
    list.appendChild(empty);
    return;
  }

  // Best matches first: names that START with the query ("squ" → Squat), then names
  // with a word starting with it (Goblet Squat), then the rest — alphabetical within each
  const rank = (ex) => {
    if (!query) return 0;
    const name = normalize(ex.name);
    if (name.startsWith(query)) return 0;
    if (name.split(/[\s-]+/).some(word => word.startsWith(query))) return 1;
    return 2;
  };

  filtered
    .slice()
    .sort((a, b) => rank(a) - rank(b) || a.name.localeCompare(b.name))
    .forEach(ex => {
      // Built with textContent, never innerHTML: names are inserted as text, not markup
      const li = document.createElement('li');
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'picker-item';

      const name = document.createElement('span');
      name.className = 'picker-item-name';
      name.textContent = ex.name;

      const meta = document.createElement('span');
      meta.className = 'picker-item-meta';
      meta.textContent = ex.equipment || '';

      btn.append(name, meta);
      btn.addEventListener('click', () => selectExerciseForRow(ex));
      li.appendChild(btn);
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
    // Log rows only: picking Bench on a row right under Bench rows merges it into that block
  if (row.closest('#sets-container')) regroupSetRows();

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