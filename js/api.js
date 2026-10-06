/* ============================================================
   RECOVERY INTERFACE — API Transport Layer
   ============================================================ */

export async function fetchMe() {
  const res = await fetch('pages/api_me.php');
  if (!res.ok) throw new Error('Failed to load user info');
  return res.json();
}

export async function loginApi(payload) {
  const res = await fetch('pages/api_login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Login failed');
  return data;
}

export async function registerApi(payload) {
  const res = await fetch('pages/api_register.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Registration failed');
  return data;
}

export async function logoutApi() {
  const res = await fetch('pages/api_logout.php', { method: 'POST' });
  return res.json();
}

export async function fetchMuscles() {
  const res = await fetch('pages/api_muscles.php');
  if (!res.ok) throw new Error('Failed to load muscle data');
  return res.json();
}

export async function fetchExercises() {
  const res = await fetch('pages/api_exercises.php');
  if (!res.ok) throw new Error('Failed to load exercise library');
  return res.json();
}

export async function fetchHistory() {
  const res = await fetch('pages/api_workout_history.php?limit=9');
  if (!res.ok) throw new Error('Failed to load workout history');
  return res.json();
}

export async function postWorkout(payload) {
  const res = await fetch('pages/api_log_workout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to save workout');
  return data;
}

export async function postPreview(payload) {
  const res = await fetch('pages/api_preview_workout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to preview workout');
  return data;
}

export async function fetchWorkoutById(workoutId) {
  const res = await fetch(`pages/api_get_workout.php?id=${workoutId}`);
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to load workout');
  return data.workout;
}

export async function updateWorkout(workoutId, payload) {
  const res = await fetch('pages/api_update_workout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ workout_id: workoutId, ...payload }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to update workout');
  return data;
}

export async function deleteWorkoutApi(workoutId) {
  const res = await fetch('pages/api_delete_workout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ workout_id: workoutId }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to delete workout');
  return data;
}

export async function fetchProfile() {
  const res = await fetch('pages/api_get_profile.php');
  if (!res.ok) throw new Error('Failed to load profile');
  return res.json();
}

export async function saveProfileApi(payload) {
  const res = await fetch('pages/api_save_profile.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Failed to save profile');
  return data;
}

export async function fetchAnalytics() {
  const res = await fetch('pages/api_analytics.php');
  if (!res.ok) throw new Error('Failed to load analytics data');
  return res.json();
}