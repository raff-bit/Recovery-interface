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
  const rotator = document.getElementById('hologram-rotator');

  const maleFront = document.getElementById('body-front');
  const maleBack = document.getElementById('body-back');
  const femaleFront = document.getElementById('body-front-female');
  const femaleBack = document.getElementById('body-back-female');

  // Alterna silhueta masculina / feminina
  if (maleFront) maleFront.style.display = isFemale ? 'none' : 'block';
  if (maleBack) maleBack.style.display = isFemale ? 'none' : 'block';
  if (femaleFront) femaleFront.style.display = isFemale ? 'block' : 'none';
  if (femaleBack) femaleBack.style.display = isFemale ? 'block' : 'none';

  // Roda tridimensionalmente no eixo Y
  if (rotator) {
    rotator.style.transform = state.hologramView === 'back' ? 'rotateY(180deg)' : 'rotateY(0deg)';
  }

  const btn = document.getElementById('toggle-view');
  if (btn) {
    btn.textContent = state.hologramView === 'front' ? '⟲ Rotate (180°)' : '⟲ Face Front';
  }
}

export function handleViewToggle() {
  state.hologramView = state.hologramView === 'front' ? 'back' : 'front';
  updateHologramVisibility();
}

/**
 * Ativa o controlo tátil/rato para girar o holograma no ar livremente (Drag to Spin)
 */
export function initHologram3DInteractions() {
  const stage = document.getElementById('hologram-stage');
  const rotator = document.getElementById('hologram-rotator');
  if (!stage || !rotator) return;

  let isDragging = false;
  let startX = 0;
  let currentDelta = 0;

  const startDrag = (clientX) => {
    isDragging = true;
    startX = clientX;
    rotator.style.transition = 'none'; // Resposta instantânea ao arrasto
  };

  const moveDrag = (clientX) => {
    if (!isDragging) return;
    currentDelta = clientX - startX;
    const baseAngle = state.hologramView === 'back' ? 180 : 0;
    // Rotação proporcional ao movimento em píxeis
    const liveAngle = baseAngle + (currentDelta * 0.8);
    rotator.style.transform = `rotateY(${liveAngle}deg)`;
  };

  const endDrag = () => {
    if (!isDragging) return;
    isDragging = false;
    rotator.style.transition = 'transform 0.65s cubic-bezier(0.2, 0.85, 0.32, 1.2)';

    // Se arrastou mais de 45px para qualquer lado, inverte a vista
    if (Math.abs(currentDelta) > 45) {
      state.hologramView = state.hologramView === 'front' ? 'back' : 'front';
    }
    updateHologramVisibility();
    currentDelta = 0;
  };

  // Eventos de Rato (PC)
  stage.addEventListener('mousedown', (e) => startDrag(e.clientX));
  window.addEventListener('mousemove', (e) => moveDrag(e.clientX));
  window.addEventListener('mouseup', endDrag);

  // Eventos de Toque (Mobile / Tablets)
  stage.addEventListener('touchstart', (e) => {
    if (e.touches.length === 1) startDrag(e.touches[0].clientX);
  }, { passive: true });
  window.addEventListener('touchmove', (e) => {
    if (e.touches.length === 1) moveDrag(e.touches[0].clientX);
  }, { passive: true });
  window.addEventListener('touchend', endDrag);
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