/* ============================================================
   RECOVERY — Body Map, Readiness Score & Muscle List
   ============================================================ */

import { state } from './state.js';
import { fetchMuscles } from './api.js';
import { readinessColor, countUp, summarizeReadiness } from './ui.js';

const SCORE_CIRCUMFERENCE = 2 * Math.PI * 52;
let lastScore = 0;

export function renderMuscleList(muscles) {
  const list = document.getElementById('muscle-list');
  if (!list) return;
  list.innerHTML = '';

  // Mais fatigados primeiro: é o que interessa ver ao abrir a app
  const sorted = muscles.slice().sort((a, b) => a.readiness - b.readiness);

  sorted.forEach((m, index) => {
    const color = readinessColor(m.readiness);
    const li = document.createElement('li');
    li.className = 'muscle-row';
    li.dataset.muscle = m.muscle_key;
    li.style.setProperty('--i', index);

    li.innerHTML = `
      <div class="muscle-row-main">
        <span class="muscle-row-name">${m.display_name}</span>
        <span class="muscle-row-status">${m.status}</span>
      </div>
      <span class="muscle-row-value" style="color:${m.readiness >= 90 ? 'var(--ink)' : color}"><span class="num">0</span><small>%</small></span>
      <div class="muscle-row-bar-track">
        <div class="muscle-row-bar-fill" style="width:${m.readiness}%; background:${color}"></div>
      </div>
    `;
    li.addEventListener('mouseenter', () => setMuscleFocus(m.muscle_key));
    li.addEventListener('mouseleave', () => setMuscleFocus(null));
    list.appendChild(li);

    countUp(li.querySelector('.num'), m.readiness, { duration: 900 + index * 25 });
  });
}

export function applyHologramColors(muscles) {
  muscles.forEach(m => {
    const shapes = document.querySelectorAll(`.muscle-shape[data-muscle="${m.muscle_key}"]`);
    const color = readinessColor(m.readiness);
    // Só os músculos em recuperação brilham: quanto mais fatigado, mais forte
    const glow = m.readiness < 70 ? 2 + ((70 - m.readiness) / 70) * 10 : 0;
    // Músculos recuperados recuam visualmente para os pontos quentes sobressaírem
    const opacity = m.readiness >= 70 ? 1 - ((m.readiness - 70) / 30) * 0.5 : 1;

    shapes.forEach(shape => {
      shape.style.fill = color;
      shape.style.fillOpacity = opacity.toFixed(2);
      shape.style.filter = glow ? `drop-shadow(0 0 ${glow.toFixed(1)}px ${color})` : 'none';
      shape.classList.toggle('critical-pulse', m.readiness < 35);
    });
  });
}

export function updateReadinessHero(muscles) {
  const { score, title, sub } = summarizeReadiness(muscles);

  const ring = document.getElementById('score-progress');
  if (ring) {
    ring.style.strokeDashoffset = SCORE_CIRCUMFERENCE * (1 - score / 100);
    ring.style.stroke = readinessColor(score);
  }

  countUp(document.getElementById('score-value'), score, { from: lastScore, duration: 1400 });
  lastScore = score;

  const titleEl = document.getElementById('readiness-title');
  const subEl = document.getElementById('readiness-sub');
  const kickerEl = document.getElementById('readiness-kicker');
  if (titleEl) titleEl.textContent = title;
  if (subEl) subEl.textContent = sub;
  if (kickerEl) {
    kickerEl.textContent = 'Today · ' + new Date().toLocaleDateString(undefined, {
      weekday: 'short', day: 'numeric', month: 'short',
    });
  }
}

/**
 * Destaca um músculo no corpo e na lista em simultâneo (null limpa o destaque)
 */
export function setMuscleFocus(muscleKey) {
  const stage = document.getElementById('hologram-stage');
  if (stage) stage.classList.toggle('has-focus', Boolean(muscleKey));

  document.querySelectorAll('.muscle-shape.is-focus, .muscle-row.is-focus')
    .forEach(el => el.classList.remove('is-focus'));

  if (!muscleKey) return;
  document.querySelectorAll(`.muscle-shape[data-muscle="${muscleKey}"], .muscle-row[data-muscle="${muscleKey}"]`)
    .forEach(el => el.classList.add('is-focus'));
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
    btn.textContent = state.hologramView === 'front' ? 'Back view' : 'Front view';
  }
}

export function handleViewToggle() {
  state.hologramView = state.hologramView === 'front' ? 'back' : 'front';
  updateHologramVisibility();
}

/**
 * Recolhe/expande o mapa corporal (só visível no layout de telemóvel)
 */
export function toggleHologramCollapse() {
  const panel = document.getElementById('hologram-panel');
  const btn = document.getElementById('hologram-collapse-btn');
  if (!panel || !btn) return;

  const collapsed = panel.classList.toggle('collapsed');
  btn.title = collapsed ? 'Show body map' : 'Hide body map';
  btn.setAttribute('aria-expanded', String(!collapsed));
}

/**
 * Etiqueta flutuante com o nome e a prontidão do músculo (rato e toque)
 */
function initBodyTip(stage) {
  const tip = document.getElementById('body-tip');
  if (!tip) return;
  let hideTimer = null;

  // Troca os <title> nativos (tooltip duplicado e lento) por aria-label
  stage.querySelectorAll('.muscle-shape title').forEach(title => {
    title.parentElement.setAttribute('aria-label', title.textContent);
    title.remove();
  });

  const show = (shape, clientX, clientY) => {
    const key = shape.dataset.muscle;
    const muscle = (state.muscles || []).find(m => m.muscle_key === key);
    if (!muscle) return;

    const rect = stage.getBoundingClientRect();
    tip.innerHTML = `<span>${muscle.display_name}</span><span class="body-tip-value" style="color:${readinessColor(muscle.readiness)}">${muscle.readiness}%</span>`;
    tip.style.left = (clientX - rect.left) + 'px';
    tip.style.top = (clientY - rect.top) + 'px';
    tip.classList.add('visible');
    setMuscleFocus(key);
  };

  const hide = () => {
    tip.classList.remove('visible');
    setMuscleFocus(null);
  };

  stage.addEventListener('pointermove', (e) => {
    if (e.pointerType !== 'mouse') return;
    const shape = e.target.closest('.muscle-shape');
    if (shape) show(shape, e.clientX, e.clientY);
    else if (tip.classList.contains('visible')) hide();
  });
  stage.addEventListener('pointerleave', (e) => {
    if (e.pointerType === 'mouse') hide();
  });

  // Toque: mostra durante uns segundos
  stage.addEventListener('click', (e) => {
    const shape = e.target.closest('.muscle-shape');
    if (!shape || e.pointerType === 'mouse') return;
    show(shape, e.clientX, e.clientY);
    clearTimeout(hideTimer);
    hideTimer = setTimeout(hide, 2200);
  });
}

/**
 * Ativa o controlo tátil/rato para girar o corpo (drag to spin)
 */
export function initHologram3DInteractions() {
  const stage = document.getElementById('hologram-stage');
  const rotator = document.getElementById('hologram-rotator');
  if (!stage || !rotator) return;

  initBodyTip(stage);

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
    rotator.style.transition = '';

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
  updateReadinessHero(state.muscles);

  const updated = document.getElementById('last-updated');
  if (updated) {
    updated.textContent = 'Updated ' + new Date(data.generated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
}
