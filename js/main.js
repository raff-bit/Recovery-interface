/* ============================================================
   RECOVERY INTERFACE — Master Application Entry Point
   ============================================================ */

import { state, LBS_PER_KG, IN_PER_CM } from './state.js';
import { fetchExercises } from './api.js';
import { handleViewToggle, refreshMuscles } from './hologram.js';
import { refreshAnalytics } from './analytics.js';
import { closeExercisePicker, closeWeightHelp } from './picker.js';
import { addSetRow, handleWorkoutSubmit, exitEditMode, refreshHistory, showFeedback } from './workout.js';
import { addPlannerSetRow, handlePreviewClick, handleClearPreview } from './planner.js';
import { openProfileModal, closeProfileModal, handleProfileSubmit, loadProfile, updateProfileUnitLabels } from './profile.js';
import {
  checkAuthSession,
  openAuthModal,
  closeAuthModal,
  switchAuthTab,
  handleLoginSubmit,
  handleRegisterSubmit,
  handleLogout
} from './auth.js';

export async function loadExercises() {
  const data = await fetchExercises();
  state.exercises = data.exercises;
}

export function startClock() {
  const clock = document.getElementById('clock');
  if (!clock) return;
  const tick = () => { clock.textContent = new Date().toLocaleTimeString(); };
  tick();
  setInterval(tick, 1000);
}

const BOOT_MESSAGES = [
  'INITIALIZING RECOVERY INTERFACE...',
  'ESTABLISHING DATABASE LINK...',
  'LOADING EXERCISE LIBRARY...',
  'SCANNING MUSCLE TELEMETRY...',
  'CALIBRATING FATIGUE MODELS...',
  'SYNCING SESSION HISTORY...',
  'SYSTEMS NOMINAL.',
];

function wait(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function typeBootLine(text) {
  return new Promise(resolve => {
    const log = document.getElementById('boot-log');
    if (!log) return resolve();
    const li = document.createElement('li');
    li.className = 'boot-log-line';
    log.appendChild(li);

    let i = 0;
    const interval = setInterval(() => {
      li.textContent += text[i];
      i++;
      if (i >= text.length) {
        clearInterval(interval);
        li.classList.add('done');
        resolve();
      }
    }, 14);
  });
}

function setBootProgress(percent) {
  const clamped = Math.min(100, Math.max(0, percent));
  const percentEl = document.getElementById('boot-percent');
  const barFillEl = document.getElementById('boot-bar-fill');
  if (percentEl) percentEl.textContent = Math.round(clamped) + '%';
  if (barFillEl) barFillEl.style.width = clamped + '%';

  const ring = document.getElementById('boot-ring-progress');
  if (ring) {
    const circumference = 2 * Math.PI * 90;
    ring.style.strokeDashoffset = circumference * (1 - clamped / 100);
  }
}

async function runBootAnimation() {
  const ring = document.getElementById('boot-ring-progress');
  if (ring) {
    const circumference = 2 * Math.PI * 90;
    ring.style.strokeDasharray = circumference;
    ring.style.strokeDashoffset = circumference;
  }
  setBootProgress(0);

  for (let i = 0; i < BOOT_MESSAGES.length; i++) {
    await typeBootLine(BOOT_MESSAGES[i]);
    setBootProgress(((i + 1) / BOOT_MESSAGES.length) * 100);
    await wait(120);
  }
}

async function completeBoot() {
  const bootScreen = document.getElementById('boot-screen');
  const hudFrame = document.getElementById('hud-frame');

  if (bootScreen) bootScreen.classList.add('boot-complete');
  if (hudFrame) hudFrame.classList.remove('pre-reveal');
  document.body.classList.remove('booting');

  await wait(700);
  if (bootScreen) bootScreen.style.display = 'none';
}

function bindEvents() {
  document.getElementById('toggle-view').addEventListener('click', handleViewToggle);
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

  const bootAnimation = runBootAnimation();

  const dataLoad = (async () => {
    try {
      await checkAuthSession();
      await loadProfile();
      await loadExercises();
      addSetRow();

      const plannerDateInput = document.getElementById('planner-date');
      if (plannerDateInput) {
        const today = new Date().toISOString().slice(0, 10);
        plannerDateInput.value = today;
        plannerDateInput.min = today;
      }
      addPlannerSetRow();

      await refreshMuscles();
      await refreshHistory();
      await refreshAnalytics();
    } catch (err) {
      console.error(err);
      showFeedback('Could not reach the backend — check XAMPP is running.', true);
    }
  })();

  await Promise.all([bootAnimation, dataLoad]);
  await completeBoot();
}

document.addEventListener('DOMContentLoaded', init);