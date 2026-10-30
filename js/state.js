/* ============================================================
   RECOVERY INTERFACE — Central Application State & Conversions
   ============================================================ */

export const state = {
  user: null,
  muscles: [],
  exercises: [],
  profile: null,
  hologramView: 'front', // 'front' | 'back'
  unitSystem: localStorage.getItem('recovery_unit_system') || 'metric',
  activePickerRow: null,
  activePickerRegion: 'all',
  editingWorkoutId: null,
  routines: [],           // your routines + built-in templates (js/routines.js)
};

export const MUSCLE_GROUPS = [
  { key: 'all',       label: 'All' },
  { key: 'chest',     label: 'Chest' },
  { key: 'back',      label: 'Back' },
  { key: 'shoulders', label: 'Shoulders' },
  { key: 'arms',      label: 'Arms' },
  { key: 'core',      label: 'Core' },
  { key: 'legs',      label: 'Legs' },
];

export const LBS_PER_KG = 2.20462;
export const IN_PER_CM = 0.393701;

export function kgToDisplayWeight(kg) {
  if (kg === null || kg === undefined || isNaN(kg)) return '';
  return state.unitSystem === 'imperial'
    ? Math.round(kg * LBS_PER_KG * 10) / 10
    : Math.round(kg * 10) / 10;
}

export function displayWeightToKg(val) {
  if (!val || isNaN(val)) return 0;
  return state.unitSystem === 'imperial'
    ? Math.round((val / LBS_PER_KG) * 10) / 10
    : Math.round(val * 10) / 10;
}

export function cmToDisplayHeight(cm) {
  if (cm === null || cm === undefined || isNaN(cm)) return '';
  return state.unitSystem === 'imperial'
    ? Math.round(cm * IN_PER_CM * 10) / 10
    : Math.round(cm * 10) / 10;
}

export function displayHeightToCm(val) {
  if (!val || isNaN(val)) return null;
  return state.unitSystem === 'imperial'
    ? Math.round((val / IN_PER_CM) * 10) / 10
    : Math.round(val * 10) / 10;
}

export function getWeightUnitLabel() {
  return state.unitSystem === 'imperial' ? 'lbs' : 'kg';
}