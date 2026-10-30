/* ============================================================
   RECOVERY INTERFACE — Session Planner & Future Readiness
   ============================================================ */

import { state, getWeightUnitLabel, displayWeightToKg } from './state.js';
import { postPreview } from './api.js';
import { openExercisePicker } from './picker.js';
import { refreshMuscles } from './hologram.js';
import { readinessColor, thermalFill } from './ui.js';

/** Adds a planner row and returns it (routines.js fills it in). */
export function addPlannerSetRow() {
  const container = document.getElementById('planner-sets-container');
  if (!container) return null;
  const row = document.createElement('div');
  row.className = 'set-row planner-set-row';
  const unit = getWeightUnitLabel();
  row.innerHTML = `
    <button type="button" class="exercise-picker-btn">Choose Exercise</button>
    <input type="hidden" class="set-exercise" value="">
    <label class="set-field" data-field="sets">
      <span class="set-field-label">Sets</span>
      <input type="number" class="set-count" placeholder="Sets" min="1" value="1" inputmode="numeric">
    </label>
    <label class="set-field" data-field="reps">
      <span class="set-field-label">Reps</span>
      <input type="number" class="planner-set-reps" placeholder="Reps" min="1" value="10" inputmode="numeric">
    </label>
    <label class="set-field" data-field="weight">
      <span class="set-field-label">${unit}</span>
      <input type="number" class="planner-set-weight" placeholder="${unit}" min="0" step="0.5" inputmode="decimal">
    </label>
    <label class="set-field" data-field="rpe">
      <span class="set-field-label">RPE</span>
      <input type="number" class="set-rpe" placeholder="RPE" min="1" max="10" step="0.5" value="7.5" inputmode="decimal">
    </label>
    <button type="button" class="remove-set-btn" title="Remove">×</button>
  `;
  row.querySelector('.exercise-picker-btn').addEventListener('click', () => openExercisePicker(row));
  row.querySelector('.remove-set-btn').addEventListener('click', () => row.remove());
  container.appendChild(row);
  return row;
}

export function collectPlannerSets() {
  const rows = document.querySelectorAll('#planner-sets-container .planner-set-row');
  const sets = [];

  rows.forEach(row => {
    const exerciseId = row.querySelector('.set-exercise').value;
    const count = parseInt(row.querySelector('.set-count').value, 10) || 1;
    const repsRaw = row.querySelector('.planner-set-reps').value;
    const weightRaw = row.querySelector('.planner-set-weight').value;
    const rpe = row.querySelector('.set-rpe').value;

    if (!exerciseId) return;

    const reps = repsRaw ? parseInt(repsRaw, 10) : 10;
    const weightInKg = weightRaw ? displayWeightToKg(parseFloat(weightRaw)) : 0;

    for (let i = 0; i < count; i++) {
      sets.push({
        exercise_id: parseInt(exerciseId, 10),
        reps: reps,
        weight_kg: weightInKg,
        rpe: rpe ? parseFloat(rpe) : null,
      });
    }
  });

  return sets;
}

export function showPlannerFeedback(message, isError) {
  const el = document.getElementById('planner-feedback');
  if (!el) return;
  el.textContent = message;
  el.className = 'log-feedback ' + (isError ? 'error' : 'success');
}

export function renderPlannerResults(muscles, targetDateIso) {
  const list = document.getElementById('planner-results');
  if (!list) return;
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

  affected.forEach((m, index) => {
    const li = document.createElement('li');
    li.className = 'muscle-row planner-result-row';
    li.style.setProperty('--i', index);
    li.innerHTML = `
      <span class="muscle-row-name">${m.display_name}</span>
      <span class="planner-result-values">
        <span class="before" style="color:${readinessColor(m.readiness_before)}">${m.readiness_before}%</span>
        <span class="arrow">→</span>
        <span style="color:${readinessColor(m.readiness_after)}">${m.readiness_after}%</span>
      </span>
    `;
    list.appendChild(li);
  });
}

export function applyPreviewColors(muscles) {
  muscles.filter(m => m.affected).forEach(m => {
    const shapes = document.querySelectorAll(`.muscle-shape[data-muscle="${m.muscle_key}"]`);
        const color = thermalFill(m.readiness_after);
    shapes.forEach(shape => {
      shape.style.fill = color;
      shape.style.filter = `drop-shadow(0 0 8px ${color})`;
    });
  });
}

export async function handlePreviewClick() {
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
    document.getElementById('clear-preview-btn').style.display = 'inline-flex';
    showPlannerFeedback('', false);
  } catch (err) {
    showPlannerFeedback(err.message, true);
  }
}

export async function handleClearPreview() {
  document.getElementById('clear-preview-btn').style.display = 'none';
  document.getElementById('planner-results').innerHTML = '';
  showPlannerFeedback('', false);
  await refreshMuscles();
}