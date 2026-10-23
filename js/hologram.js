/* ============================================================
   RECOVERY — Body Map, Readiness Score & Muscle List
   ============================================================ */

import { state } from './state.js';
import { fetchMuscles } from './api.js';
import { readinessColor, thermalFill, countUp, summarizeReadiness, prefersReducedMotion } from './ui.js';
import { buildBodyMap } from './bodymap.js';

const SCAN_MS = 1700;
let lastScore = 0;
let scanTimer = null;

export function renderMuscleList(muscles) {
  const list = document.getElementById('muscle-list');
  if (!list) return;
  list.innerHTML = '';

  // Most fatigued first: that's what you want to see when you open the app
  const sorted = muscles.slice().sort((a, b) => a.readiness - b.readiness);

  sorted.forEach((m, index) => {
    const color = readinessColor(m.readiness);
    const li = document.createElement('li');
    li.className = 'muscle-row';
    li.dataset.muscle = m.muscle_key;
    li.style.setProperty('--i', index);
    li.style.setProperty('--heat', color);

    li.innerHTML = `
      <div class="muscle-row-main">
        <span class="muscle-row-name">${m.display_name}</span>
        <span class="muscle-row-status">${m.status}</span>
      </div>
      <span class="muscle-row-value"><span class="num">0</span><small>%</small></span>
      <div class="muscle-row-bar-track">
        <div class="muscle-row-bar-fill" style="width:${m.readiness}%"></div>
      </div>
    `;
    li.addEventListener('mouseenter', () => setMuscleFocus(m.muscle_key));
    li.addEventListener('mouseleave', () => setMuscleFocus(null));
    list.appendChild(li);

    countUp(li.querySelector('.num'), m.readiness, { duration: 900 + index * 25 });
  });
}

/**
 * Paints every muscle with its thermal colour.
 * Hot (fatigued) muscles also get a soft glow, like heat bloom on a thermal camera.
 */
export function applyHologramColors(muscles) {
  muscles.forEach(m => {
    const shapes = document.querySelectorAll(`.muscle-shape[data-muscle="${m.muscle_key}"]`);
    const color = thermalFill(m.readiness);
    const glow = m.readiness < 70 ? 2 + ((70 - m.readiness) / 70) * 9 : 0;

    shapes.forEach(shape => {
      shape.style.fill = color;
      shape.style.filter = glow ? `drop-shadow(0 0 ${glow.toFixed(1)}px ${color})` : 'none';
      shape.classList.toggle('critical-pulse', m.readiness < 35);
    });
  });
}

/**
 * The scan: a beam sweeps down the body and each muscle changes colour
 * as the beam passes it. Runs on load and every time the data refreshes.
 */
function scanBody(muscles) {
  const stage = document.getElementById('hologram-stage');
  if (!stage || prefersReducedMotion()) {
    applyHologramColors(muscles);
    return;
  }

  // How far down the body each visible muscle sits (0 = head, 1 = feet)
  stage.querySelectorAll('.hologram-svg').forEach(svg => {
    if (svg.style.display === 'none') return;
    svg.querySelectorAll('.muscle-shape').forEach(shape => {
      const box = shape.getBBox();
      shape.style.setProperty('--y', ((box.y + box.height / 2) / 480).toFixed(3));
    });
  });

  clearTimeout(scanTimer);
  stage.classList.remove('is-scanning');
  void stage.offsetWidth; // restart the CSS animation
  stage.classList.add('is-scanning');
  applyHologramColors(muscles);
  scanTimer = setTimeout(() => stage.classList.remove('is-scanning'), SCAN_MS + 900);
}

export function updateReadinessHero(muscles) {
  const { score, title, sub } = summarizeReadiness(muscles);

  const scoreEl = document.getElementById('score-value');
  if (scoreEl && scoreEl.parentElement) {
    scoreEl.parentElement.style.color = muscles.length ? readinessColor(score) : '';
  }
  countUp(scoreEl, score, { from: lastScore, duration: 1400 });
  lastScore = score;

  const titleEl = document.getElementById('readiness-title');
  const subEl = document.getElementById('readiness-sub');
  if (titleEl) titleEl.textContent = title;
  if (subEl) subEl.textContent = sub;
}

/**
 * Highlights one muscle on the body and in the list at the same time (null clears it)
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

  const show = (id, visible) => {
    const svg = document.getElementById(id);
    if (svg) svg.style.display = visible ? 'block' : 'none';
  };
  show('body-front', !isFemale);
  show('body-back', !isFemale);
  show('body-front-female', isFemale);
  show('body-back-female', isFemale);

  // Spin around the vertical axis
  if (rotator) {
    rotator.style.transform = state.hologramView === 'back' ? 'rotateY(180deg)' : 'rotateY(0deg)';
  }

  const btn = document.getElementById('toggle-view');
  if (btn) {
    btn.textContent = state.hologramView === 'front' ? 'Show back' : 'Show front';
  }
}

export function handleViewToggle() {
  state.hologramView = state.hologramView === 'front' ? 'back' : 'front';
  updateHologramVisibility();
}

/**
 * Collapses / expands the body map (only on the phone layout)
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
 * Floating label with the muscle's name and readiness (mouse and touch)
 */
function initBodyTip(stage) {
  const tip = document.getElementById('body-tip');
  if (!tip) return;
  let hideTimer = null;

  const show = (shape, clientX, clientY) => {
    const key = shape.dataset.muscle;
    const muscle = (state.muscles || []).find(m => m.muscle_key === key);
    if (!muscle) return;

    const rect = stage.getBoundingClientRect();
    tip.innerHTML = '';
    const name = document.createElement('span');
    name.textContent = muscle.display_name;
    const value = document.createElement('span');
    value.className = 'body-tip-value';
    value.style.color = readinessColor(muscle.readiness);
    value.textContent = `${muscle.readiness}%`;
    tip.append(name, value);

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

  // Touch: show for a couple of seconds
  stage.addEventListener('click', (e) => {
    const shape = e.target.closest('.muscle-shape');
    if (!shape || e.pointerType === 'mouse') return;
    show(shape, e.clientX, e.clientY);
    clearTimeout(hideTimer);
    hideTimer = setTimeout(hide, 2200);
  });
}

/**
 * Builds the bodies, then enables drag-to-spin with mouse or finger
 */
export function initHologram3DInteractions() {
  const stage = document.getElementById('hologram-stage');
  const rotator = document.getElementById('hologram-rotator');
  if (!stage || !rotator) return;

  buildBodyMap(rotator);
  updateHologramVisibility();
  initBodyTip(stage);

  let isDragging = false;
  let startX = 0;
  let currentDelta = 0;

  const startDrag = (clientX) => {
    isDragging = true;
    startX = clientX;
    rotator.style.transition = 'none'; // follow the pointer instantly
  };

  const moveDrag = (clientX) => {
    if (!isDragging) return;
    currentDelta = clientX - startX;
    const baseAngle = state.hologramView === 'back' ? 180 : 0;
    rotator.style.transform = `rotateY(${baseAngle + currentDelta * 0.8}deg)`;
  };

  const endDrag = () => {
    if (!isDragging) return;
    isDragging = false;
    rotator.style.transition = '';

    // Dragged more than 45px either way: flip to the other side
    if (Math.abs(currentDelta) > 45) {
      state.hologramView = state.hologramView === 'front' ? 'back' : 'front';
    }
    updateHologramVisibility();
    currentDelta = 0;
  };

  stage.addEventListener('mousedown', (e) => startDrag(e.clientX));
  window.addEventListener('mousemove', (e) => moveDrag(e.clientX));
  window.addEventListener('mouseup', endDrag);

  stage.addEventListener('touchstart', (e) => {
    if (e.touches.length === 1) startDrag(e.touches[0].clientX);
  }, { passive: true });
  window.addEventListener('touchmove', (e) => {
    if (e.touches.length === 1) moveDrag(e.touches[0].clientX);
  }, { passive: true });
  window.addEventListener('touchend', endDrag);
}

/**
 * Resolves once the boot screen is gone, so the scan plays where you can see it
 */
function whenRevealed() {
  return new Promise(resolve => {
    if (!document.body.classList.contains('booting')) return resolve(false);
    const observer = new MutationObserver(() => {
      if (!document.body.classList.contains('booting')) {
        observer.disconnect();
        resolve(true);
      }
    });
    observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
  });
}

export async function refreshMuscles() {
  const data = await fetchMuscles();
  state.muscles = data.muscles;
  updateHologramVisibility();

  // Not awaited on purpose: the app keeps loading while we wait for the reveal
  whenRevealed().then((justRevealed) => {
    const delay = justRevealed ? 450 : 0;
    setTimeout(() => {
      renderMuscleList(state.muscles);
      updateReadinessHero(state.muscles);
      scanBody(state.muscles);
    }, delay);
  });

  const updated = document.getElementById('last-updated');
  if (updated) {
    updated.textContent = 'Scanned at ' + new Date(data.generated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
}