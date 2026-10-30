/* ============================================================
   RECOVERY INTERFACE — API Transport Layer
   ------------------------------------------------------------
   Every request goes through getJson() / postJson(), so security
   rules live in ONE place instead of being copy-pasted 15 times:
     - POSTs carry the CSRF token in the X-CSRF-Token header
     - an expired token (403 csrf_invalid) is refreshed and the
       request retried once — e.g. right after logout/login
     - non-JSON error pages never crash the UI with a parse error
   ============================================================ */

let csrfToken = null;

async function parseJson(res) {
  try {
    return await res.json();
  } catch {
    return {}; // server returned HTML/empty (e.g. Apache error page)
  }
}

async function getJson(url, fallbackError) {
  const res = await fetch(url, { credentials: 'same-origin' });
  const data = await parseJson(res);
  if (!res.ok) throw new Error(data.error || fallbackError);
  return data;
}

async function postJson(url, payload, fallbackError, isRetry = false) {
  if (!csrfToken) await fetchMe();

  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
    },
    body: JSON.stringify(payload ?? {}),
  });
  const data = await parseJson(res);

  if (res.status === 403 && data.code === 'csrf_invalid' && !isRetry) {
    await fetchMe(); // session changed → get a fresh token
    return postJson(url, payload, fallbackError, true);
  }

  if (!res.ok) throw new Error(data.error || fallbackError);
  if (data.csrf_token) csrfToken = data.csrf_token; // login/register hand back the current token
  return data;
}

/* ---------------- Auth ---------------- */

export async function fetchMe() {
  const data = await getJson('pages/api_me.php', 'Failed to load user info');
  csrfToken = data.csrf_token ?? null;
  return data;
}

export const loginApi    = (payload) => postJson('pages/api_login.php', payload, 'Login failed');
export const registerApi = (payload) => postJson('pages/api_register.php', payload, 'Registration failed');
export const logoutApi   = () => postJson('pages/api_logout.php', {}, 'Logout failed');

/* ---------------- Read ---------------- */

export const fetchMuscles   = () => getJson('pages/api_muscles.php', 'Failed to load muscle data');
export const fetchExercises = () => getJson('pages/api_exercises.php', 'Failed to load exercise library');
export const fetchHistory   = () => getJson('pages/api_workout_history.php?limit=9', 'Failed to load workout history');
export const fetchProfile   = () => getJson('pages/api_get_profile.php', 'Failed to load profile');
export const fetchAnalytics = () => getJson('pages/api_analytics.php', 'Failed to load analytics data');
export const fetchRoutines  = () => getJson('pages/api_routines.php', 'Failed to load routines');

export async function fetchWorkoutById(workoutId) {
  const id = encodeURIComponent(workoutId);
  const data = await getJson(`pages/api_get_workout.php?id=${id}`, 'Failed to load workout');
  return data.workout;
}

/* ---------------- Write ---------------- */

export const postWorkout    = (payload) => postJson('pages/api_log_workout.php', payload, 'Failed to save workout');
export const postPreview    = (payload) => postJson('pages/api_preview_workout.php', payload, 'Failed to preview workout');
export const saveProfileApi = (payload) => postJson('pages/api_save_profile.php', payload, 'Failed to save profile');

export const updateWorkout = (workoutId, payload) =>
  postJson('pages/api_update_workout.php', { workout_id: workoutId, ...payload }, 'Failed to update workout');

export const deleteWorkoutApi = (workoutId) =>
  postJson('pages/api_delete_workout.php', { workout_id: workoutId }, 'Failed to delete workout');

/* ---------------- Routines ---------------- */

export const saveRoutineApi = (payload) =>
  postJson('pages/api_save_routine.php', payload, 'Failed to save routine');

export const deleteRoutineApi = (routineId) =>
  postJson('pages/api_delete_routine.php', { routine_id: routineId }, 'Failed to delete routine');
