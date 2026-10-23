/* ============================================================
   RECOVERY INTERFACE — Master Application Entry Point
   ============================================================ */

import { state, LBS_PER_KG, IN_PER_CM } from './state.js';
import { fetchExercises } from './api.js';
import { refreshAnalytics } from './analytics.js';
import { closeExercisePicker, closeWeightHelp } from './picker.js';
import { addSetRow, handleWorkoutSubmit, exitEditMode, refreshHistory, showFeedback } from './workout.js';
import { addPlannerSetRow, handlePreviewClick, handleClearPreview } from './planner.js';
import { openProfileModal, closeProfileModal, handleProfileSubmit, loadProfile, updateProfileUnitLabels } from './profile.js';
import { handleViewToggle, toggleHologramCollapse, refreshMuscles, initHologram3DInteractions } from './hologram.js';
import {
  checkAuthSession,
  openAuthModal,
  closeAuthModal,
  switchAuthTab,
  handleLoginSubmit,
  handleRegisterSubmit,
  handleLogout
} from './auth.js';

/**
 * "YYYY-MM-DD" in the user's LOCAL time zone.
 * (toISOString() is UTC: between 00:00 and 01:00 in Lisbon it returned YESTERDAY.)
 */
function localDateIso(date) {
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export async function loadExercises() {
  const data = await fetchExercises();
  state.exercises = data.exercises;
}

export function startClock() {
  const clock = document.getElementById('clock');
  if (!clock) return;
  const tick = () => {
    clock.textContent = new Date().toLocaleDateString(undefined, {
      weekday: 'long', day: 'numeric', month: 'long',
    });
  };
  tick();
  setInterval(tick, 60 * 1000);
}

const BOOT_MESSAGES = [
  'Loading your profile',
  'Loading exercises',
  'Reading recovery data',
  'Syncing your sessions',
];

function wait(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function setBootProgress(percent, message) {
  const clamped = Math.min(100, Math.max(0, percent));
  const barFillEl = document.getElementById('boot-bar-fill');
  const statusEl = document.getElementById('boot-status');
  if (barFillEl) barFillEl.style.transform = `scaleX(${clamped / 100})`;
  if (statusEl && message) statusEl.textContent = message;
}

async function runBootAnimation() {
  setBootProgress(0, BOOT_MESSAGES[0]);
  await wait(250);

  for (let i = 0; i < BOOT_MESSAGES.length; i++) {
    setBootProgress(((i + 1) / BOOT_MESSAGES.length) * 100, BOOT_MESSAGES[i]);
    await wait(170);
  }
}

async function completeBoot() {
  const bootScreen = document.getElementById('boot-screen');
  const hudFrame = document.getElementById('hud-frame');

  if (bootScreen) bootScreen.classList.add('boot-complete');
  if (hudFrame) hudFrame.classList.remove('pre-reveal');
  document.body.classList.remove('booting');

  await wait(650);
  if (bootScreen) bootScreen.style.display = 'none';
}

/* ---------- TAB NAVIGATION ---------- */

export function switchConsoleTab(tabId) {
  const dock = document.getElementById('hud-dock');
  const dockBtns = Array.from(document.querySelectorAll('.hud-dock-btn'));
  const panels = document.querySelectorAll('.hud-tab-panel');

  dockBtns.forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tabId);
  });

  // Desliza o indicador para o separador ativo
  const index = dockBtns.findIndex(btn => btn.dataset.tab === tabId);
  if (dock && index >= 0) dock.style.setProperty('--tab-index', index);

  panels.forEach(panel => {
    panel.classList.toggle('active', panel.id === tabId);
  });

  // Em telemóvel a dock está fixa em baixo: volta ao topo para mostrar o novo separador
  if (window.matchMedia('(max-width: 719px)').matches) {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

function initDockNavigation() {
  const dock = document.getElementById('hud-dock');
  if (!dock) return;

  dock.addEventListener('click', (e) => {
    const btn = e.target.closest('.hud-dock-btn');
    if (!btn) return;
    switchConsoleTab(btn.dataset.tab);
  });
}

function bindEvents() {
  document.getElementById('toggle-view').addEventListener('click', handleViewToggle);
  document.getElementById('hologram-collapse-btn').addEventListener('click', toggleHologramCollapse);
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
    if (!tooltip || tooltip.style.display !== 'block') return;
    if (event.target.classList.contains('weight-help-btn')) return;
    if (tooltip.contains(event.target)) return;
    closeWeightHelp();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeWeightHelp();
      closeExercisePicker();
      closeProfileModal();
      closeAuthModal();
    }
  });

  document.getElementById('profile-units').addEventListener('change', (e) => {
    const newUnit = e.target.value;
    const weightField = document.getElementById('profile-weight');
    const heightField = document.getElementById('profile-height');

    const currentWeight = parseFloat(weightField.value);
    const currentHeight = parseFloat(heightField.value);

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

  document.getElementById('login-open-btn').addEventListener('click', () => openAuthModal('login'));
  document.getElementById('close-auth-btn').addEventListener('click', closeAuthModal);
  document.getElementById('tab-login').addEventListener('click', () => switchAuthTab('login'));
  document.getElementById('tab-register').addEventListener('click', () => switchAuthTab('register'));
  document.getElementById('login-form').addEventListener('submit', handleLoginSubmit);
  document.getElementById('register-form').addEventListener('submit', handleRegisterSubmit);
  document.getElementById('logout-btn').addEventListener('click', handleLogout);
  document.getElementById('auth-modal').addEventListener('click', (event) => {
    if (event.target.id === 'auth-modal') closeAuthModal();
  });
}

async function init() {
  startClock();
  bindEvents();
  initDockNavigation();
  initHologram3DInteractions();

  const bootAnimation = runBootAnimation();

  const dataLoad = (async () => {
        try {
      // Requests that don't depend on each other run in PARALLEL.
      // Before: 6 requests one after another → load time = sum of all 6.
      // Now: 3 "waves" → load time ≈ the slowest request of each wave.
      //   wave 1: session (sets the cookie + CSRF token everything else relies on)
      //   wave 2: profile + exercise library (the hologram needs the profile's sex)
      //   wave 3: readiness + history + analytics
      await checkAuthSession();
      await Promise.all([loadProfile(), loadExercises()]);

      addSetRow();
      const plannerDateInput = document.getElementById('planner-date');
      if (plannerDateInput) {
        const today = localDateIso(new Date());
        plannerDateInput.value = today;
        plannerDateInput.min = today;
      }
      addPlannerSetRow();

      await Promise.all([refreshMuscles(), refreshHistory(), refreshAnalytics()]);
    } catch (err) {
      console.error(err);
      showFeedback('Could not reach the backend — check XAMPP is running.', true);
    }
  })();

  await Promise.all([bootAnimation, dataLoad]);
  await completeBoot();
}

document.addEventListener('DOMContentLoaded', init);