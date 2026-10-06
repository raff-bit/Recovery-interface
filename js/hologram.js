/* ============================================================
   RECOVERY INTERFACE — Hologram & Readiness Rendering
   ============================================================ */

import { state } from './state.js';
import { fetchMuscles } from './api.js';

export function renderMuscleList(muscles) {
  const list = document.getElementById('muscle-list');
  if (!list) return;
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

export function applyHologramColors(muscles) {
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

export function updateHologramVisibility() {
  const isFemale = state.profile && state.profile.sex === 'female';
  const isFront = state.hologramView === 'front';

  const maleFront = document.getElementById('body-front');
  const maleBack = document.getElementById('body-back');
  const femaleFront = document.getElementById('body-front-female');
  const femaleBack = document.getElementById('body-back-female');

  if (maleFront) maleFront.style.display = (!isFemale && isFront) ? '' : 'none';
  if (maleBack) maleBack.style.display = (!isFemale && !isFront) ? '' : 'none';
  if (femaleFront) femaleFront.style.display = (isFemale && isFront) ? '' : 'none';
  if (femaleBack) femaleBack.style.display = (isFemale && !isFront) ? '' : 'none';

  const btn = document.getElementById('toggle-view');
  if (btn) {
    btn.textContent = isFront ? 'Show Back' : 'Show Front';
  }
}

export function handleViewToggle() {
  state.hologramView = state.hologramView === 'front' ? 'back' : 'front';
  updateHologramVisibility();
}

export async function refreshMuscles() {
  const data = await fetchMuscles();
  state.muscles = data.muscles;
  renderMuscleList(state.muscles);
  applyHologramColors(state.muscles);

  const updated = document.getElementById('last-updated');
  if (updated) {
    updated.textContent = 'Last scan: ' + new Date(data.generated_at).toLocaleTimeString();
  }
}