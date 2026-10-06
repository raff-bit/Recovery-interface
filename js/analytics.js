/* ============================================================
   RECOVERY INTERFACE — Telemetry & Training Analytics
   ============================================================ */

import { fetchAnalytics } from './api.js';

export function renderWeeklyVolume(weeklyData) {
  const container = document.getElementById('weekly-volume-chart');
  if (!container) return;
  container.innerHTML = '';

  if (!weeklyData || weeklyData.length === 0) {
    container.innerHTML = '<p class="hologram-footnote">No volume data available.</p>';
    return;
  }

  const maxSets = Math.max(...weeklyData.map(w => w.total_sets), 1);

  weeklyData.forEach(w => {
    const col = document.createElement('div');
    col.className = 'volume-bar-col';
    const pct = w.total_sets > 0 ? Math.round((w.total_sets / maxSets) * 100) : 0;
    const dateParts = w.week_start.split('-');
    const labelDate = dateParts.length === 3 ? `${dateParts[2]}/${dateParts[1]}` : w.week_start;

    col.innerHTML = `
      <span class="volume-bar-val">${w.total_sets > 0 ? w.total_sets : ''}</span>
      <div class="volume-bar-track">
        <div class="volume-bar-fill" style="height: ${pct}%"></div>
      </div>
      <span class="volume-bar-label">${labelDate}</span>
    `;
    container.appendChild(col);
  });
}

export function renderMuscleBalance(balanceData) {
  const container = document.getElementById('muscle-balance-list');
  if (!container) return;
  container.innerHTML = '';

  if (!balanceData || balanceData.length === 0) {
    container.innerHTML = '<p class="hologram-footnote">No workout activity in the last 30 days.</p>';
    return;
  }

  balanceData.forEach(item => {
    const row = document.createElement('div');
    row.className = 'balance-item';
    row.innerHTML = `
      <div class="balance-item-header">
        <span class="balance-region-name">${item.body_region}</span>
        <span class="balance-region-meta">${item.total_sets} sets (${item.percentage}%)</span>
      </div>
      <div class="balance-bar-track">
        <div class="balance-bar-fill" style="width: ${item.percentage}%"></div>
      </div>
    `;
    container.appendChild(row);
  });
}

export function renderTopExercises(topData) {
  const container = document.getElementById('top-exercises-list');
  if (!container) return;
  container.innerHTML = '';

  if (!topData || topData.length === 0) {
    container.innerHTML = '<p class="hologram-footnote">No exercises logged in the last 30 days.</p>';
    return;
  }

  topData.forEach((item, index) => {
    const li = document.createElement('li');
    li.className = 'top-exercise-item';
    li.innerHTML = `
      <div class="top-exercise-left">
        <span class="top-exercise-rank">#${index + 1}</span>
        <span class="top-exercise-name">${item.exercise_name}</span>
      </div>
      <span class="top-exercise-sets">${item.total_sets} sets</span>
    `;
    container.appendChild(li);
  });
}

export async function refreshAnalytics() {
  try {
    const data = await fetchAnalytics();
    renderWeeklyVolume(data.weekly_volume);
    renderMuscleBalance(data.muscle_group_balance);
    renderTopExercises(data.top_exercises);
  } catch (err) {
    console.error('Failed to refresh analytics:', err);
  }
}