/* ============================================================
   RECOVERY INTERFACE — Authentication & Onboarding
   ============================================================ */

import { state } from './state.js';
import { fetchMe, loginApi, registerApi, logoutApi } from './api.js';
import { loadProfile, openProfileModal } from './profile.js';
import { refreshMuscles } from './hologram.js';
import { refreshHistory } from './workout.js';
import { refreshAnalytics } from './analytics.js';
import { loadRoutines } from './routines.js';
import { updateExportPanel } from './export.js';

export function updateAuthHeader() {
  const loggedContainer = document.getElementById('auth-header-logged');
  const loginOpenBtn = document.getElementById('login-open-btn');
  const userNameEl = document.getElementById('auth-user-name');

  if (state.user && state.user.authenticated) {
    loggedContainer.style.display = 'flex';
    loginOpenBtn.style.display = 'none';
    userNameEl.textContent = state.user.name || 'USER';
  } else {
    loggedContainer.style.display = 'none';
    loginOpenBtn.style.display = 'inline-flex';
  }
    updateExportPanel(); // export buttons only for signed-in users
}

export function openAuthModal(defaultTab = 'login') {
  switchAuthTab(defaultTab);
  document.getElementById('login-feedback').textContent = '';
  document.getElementById('register-feedback').textContent = '';
  document.getElementById('auth-modal').style.display = 'flex';
}

export function closeAuthModal() {
  document.getElementById('auth-modal').style.display = 'none';
}

export function switchAuthTab(tab) {
  const isLogin = tab === 'login';
  document.getElementById('tab-login').classList.toggle('active', isLogin);
  document.getElementById('tab-register').classList.toggle('active', !isLogin);
  document.getElementById('login-form').style.display = isLogin ? 'flex' : 'none';
  document.getElementById('register-form').style.display = isLogin ? 'none' : 'flex';
}

export async function handleLoginSubmit(event) {
  event.preventDefault();
  const feedback = document.getElementById('login-feedback');
  const email = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-password').value;

  try {
    const res = await loginApi({ email, password });
    state.user = { authenticated: true, ...res.user };
    updateAuthHeader();
    closeAuthModal();

    await loadProfile();
    await refreshMuscles();
    await refreshHistory();
    await refreshAnalytics();
    await loadRoutines();
  } catch (err) {
    feedback.textContent = err.message;
    feedback.className = 'log-feedback error';
  }
}

export async function handleRegisterSubmit(event) {
  event.preventDefault();
  const feedback = document.getElementById('register-feedback');
  const name = document.getElementById('reg-name').value.trim();
  const email = document.getElementById('reg-email').value.trim();
  const password = document.getElementById('reg-password').value;

  try {
    const res = await registerApi({ name, email, password });
    state.user = { authenticated: true, ...res.user };
    updateAuthHeader();
    closeAuthModal();

    await loadProfile();
    await refreshMuscles();
    await refreshHistory();
    await refreshAnalytics();
    await loadRoutines();

    openProfileModal();
    const profileFeedback = document.getElementById('profile-feedback');
    profileFeedback.textContent = 'Account created. Set your body weight and units to calibrate telemetry.';
    profileFeedback.className = 'log-feedback';
  } catch (err) {
    feedback.textContent = err.message;
    feedback.className = 'log-feedback error';
  }
}

export async function handleLogout() {
  try {
    await logoutApi();
    window.location.reload();
  } catch (err) {
    console.error(err);
  }
}

export async function checkAuthSession() {
  try {
    const data = await fetchMe();
    if (data.authenticated && data.user) {
      state.user = { authenticated: true, ...data.user };
    } else {
      state.user = { authenticated: false, ...(data.user || { name: 'Demo' }) };
    }
    updateAuthHeader();
  } catch (err) {
    console.error(err);
  }
}