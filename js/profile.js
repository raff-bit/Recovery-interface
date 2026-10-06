/* ============================================================
   RECOVERY INTERFACE — User Profile & Biometrics
   ============================================================ */

import {
  state,
  getWeightUnitLabel,
  kgToDisplayWeight,
  displayWeightToKg,
  cmToDisplayHeight,
  displayHeightToCm
} from './state.js';
import { fetchProfile, saveProfileApi } from './api.js';
import { updateHologramVisibility, refreshMuscles } from './hologram.js';
import { refreshHistory } from './workout.js';
import { refreshAnalytics } from './analytics.js';

export function updateProfileUnitLabels() {
  const isImperial = state.unitSystem === 'imperial';
  const unit = getWeightUnitLabel();

  const labelWeight = document.getElementById('label-profile-weight');
  if (labelWeight) {
    labelWeight.innerHTML = isImperial ? 'Body Weight (lbs) *' : 'Body Weight (kg) *';
  }

  const labelHeight = document.getElementById('label-profile-height');
  if (labelHeight) {
    labelHeight.innerHTML = isImperial
      ? 'Height (in) <span class="profile-optional">(optional)</span>'
      : 'Height (cm) <span class="profile-optional">(optional)</span>';
  }

  document.querySelectorAll('.set-row .set-weight').forEach(input => {
    input.placeholder = unit;
  });

  const plannerWeightHdr = document.getElementById('planner-weight-header');
  if (plannerWeightHdr) {
    plannerWeightHdr.textContent = `Weight (${unit})`;
  }
  document.querySelectorAll('#planner-sets-container .planner-set-weight').forEach(input => {
    input.placeholder = unit;
  });
}

export function updateProfileIndicator() {
  const indicator = document.getElementById('profile-indicator');
  if (!indicator) return;
  const isComplete = Boolean(state.profile && state.profile.body_weight_kg > 0);
  indicator.style.display = isComplete ? 'none' : 'inline-block';
}

export function openProfileModal() {
  const p = state.profile;
  document.getElementById('profile-units').value = state.unitSystem;
  updateProfileUnitLabels();

  document.getElementById('profile-sex').value = p ? p.sex : 'unspecified';

  const displayedWeight = p && p.body_weight_kg ? kgToDisplayWeight(parseFloat(p.body_weight_kg)) : '';
  const displayedHeight = p && p.height_cm ? cmToDisplayHeight(parseFloat(p.height_cm)) : '';

  document.getElementById('profile-weight').value = displayedWeight;
  document.getElementById('profile-height').value = displayedHeight;
  document.getElementById('profile-age').value = p && p.age ? p.age : '';
  document.getElementById('profile-activity').value = p ? p.activity_level : 'moderately_active';

  const feedback = document.getElementById('profile-feedback');
  feedback.textContent = '';
  feedback.className = 'log-feedback';

  document.getElementById('profile-modal').style.display = 'flex';
}

export function closeProfileModal() {
  document.getElementById('profile-modal').style.display = 'none';
}

export async function handleProfileSubmit(event) {
  event.preventDefault();
  const feedback = document.getElementById('profile-feedback');
  const unitSelect = document.getElementById('profile-units').value;
  const sex = document.getElementById('profile-sex').value;
  const weightInput = parseFloat(document.getElementById('profile-weight').value);
  const heightRaw = document.getElementById('profile-height').value;
  const ageRaw = document.getElementById('profile-age').value;
  const activity = document.getElementById('profile-activity').value;

  state.unitSystem = unitSelect;
  localStorage.setItem('recovery_unit_system', unitSelect);
  updateProfileUnitLabels();

  const weightKg = displayWeightToKg(weightInput);
  const heightCm = heightRaw ? displayHeightToCm(parseFloat(heightRaw)) : null;
  const age = ageRaw ? parseInt(ageRaw, 10) : null;

  if (!weightKg || weightKg <= 0 || weightKg > 400) {
    feedback.textContent = `Please enter a valid body weight (${getWeightUnitLabel()}).`;
    feedback.className = 'log-feedback error';
    return;
  }

  if (age !== null && (age < 12 || age > 120)) {
    feedback.textContent = 'Please enter a valid age between 12 and 120.';
    feedback.className = 'log-feedback error';
    return;
  }

  try {
    const res = await saveProfileApi({
      sex: sex,
      body_weight_kg: weightKg,
      height_cm: heightCm,
      age: age,
      activity_level: activity,
    });
    state.profile = res.profile;
    updateProfileIndicator();
    updateHologramVisibility();
    feedback.textContent = 'Profile saved.';
    feedback.className = 'log-feedback success';

    await refreshMuscles();
    await refreshHistory();
    await refreshAnalytics();
    setTimeout(closeProfileModal, 600);
  } catch (err) {
    feedback.textContent = err.message;
    feedback.className = 'log-feedback error';
  }
}

export async function loadProfile() {
  try {
    const data = await fetchProfile();
    state.profile = data.profile;
    updateProfileIndicator();
    updateHologramVisibility();
  } catch (err) {
    console.error(err);
  }
}