/* ============================================================
   RECOVERY INTERFACE — Telemetry & Training Analytics
   ============================================================ */

import { fetchAnalytics } from './api.js';
import { el } from './ui.js';

// Server data goes in with textContent (via el), never through innerHTML templates.

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

    const track = el('div', 'volume-bar-track');
    const fill = el('div', 'volume-bar-fill');
    fill.style.height = `${pct}%`;
    track.append(fill);
    col.append(
      el('span', 'volume-bar-val', w.total_sets > 0 ? w.total_sets : ''),
      track,
      el('span', 'volume-bar-label', labelDate),
    );
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
    const header = el('div', 'balance-item-header');
    header.append(
      el('span', 'balance-region-name', item.body_region),
      el('span', 'balance-region-meta', `${item.total_sets} sets (${item.percentage}%)`),
    );
    const track = el('div', 'balance-bar-track');
    const fill = el('div', 'balance-bar-fill');
    fill.style.width = `${item.percentage}%`;
    track.append(fill);
    row.append(header, track);
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
    const left = el('div', 'top-exercise-left');
    left.append(
      el('span', 'top-exercise-rank', `#${index + 1}`),
      el('span', 'top-exercise-name', item.exercise_name),
    );
    li.append(left, el('span', 'top-exercise-sets', `${item.total_sets} sets`));
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