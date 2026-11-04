/* ============================================================
   RECOVERY — Shared UI Helpers (thermal scale, counters, copy)

   The app uses one colour scale, borrowed from thermal cameras:
   recovered muscles read "cold" (indigo), freshly trained ones
   read "hot" (magenta -> ember -> amber -> white-hot).
   ============================================================ */

// Muscle paint on the body map. The cold end is deliberately dark
// so recovered muscles recede and the hot spots stand out.
const FILL_STOPS = [
  [0.00, '#232a5c'],
  [0.15, '#33307a'],
  [0.35, '#6e2a9a'],
  [0.55, '#b8287c'],
  [0.72, '#ee4e2e'],
  [0.87, '#ff9d1e'],
  [1.00, '#ffe8a3'],
];

// The same scale, lightened so it stays readable as text on the dark UI.
const INK_STOPS = [
  [0.00, '#9ba5dd'],
  [0.30, '#b49af2'],
  [0.50, '#f07cc8'],
  [0.70, '#ff7a4d'],
  [0.86, '#ffa83a'],
  [1.00, '#ffd77a'],
];

function hexToRgb(hex) {
  const n = parseInt(hex.slice(1), 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function sampleStops(stops, t) {
  const x = Math.max(0, Math.min(1, t));
  for (let i = 1; i < stops.length; i++) {
    const [t1, c1] = stops[i];
    if (x <= t1) {
      const [t0, c0] = stops[i - 1];
      const k = (x - t0) / (t1 - t0);
      const a = hexToRgb(c0);
      const b = hexToRgb(c1);
      const mix = a.map((v, j) => Math.round(v + (b[j] - v) * k));
      return `rgb(${mix[0]} ${mix[1]} ${mix[2]})`;
    }
  }
  return stops[stops.length - 1][1];
}

// 0 = fully fatigued (hot), 100 = fully recovered (cold)
const fatigueOf = (readiness) => 1 - Math.max(0, Math.min(100, Number(readiness) || 0)) / 100;

/**
 * Readable colour for numbers, bars and labels.
 */
/**
 * Creates an element whose content is set with textContent — never parsed as HTML.
 * Use it for anything that comes from the server, even "trusted" reference data:
 * exercise and muscle names could one day be user-created.
 */
export function el(tag, className = '', text = null) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== null) node.textContent = String(text);
  return node;
}

export function readinessColor(readiness) {
  return sampleStops(INK_STOPS, fatigueOf(readiness));
}

/**
 * Deep thermal colour used to paint muscles on the body map.
 */
export function thermalFill(readiness) {
  return sampleStops(FILL_STOPS, fatigueOf(readiness));
}

const prefersReducedMotion = () =>
  window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export { prefersReducedMotion };

/**
 * Animates a number from 'from' to 'to' (easeOutExpo)
 */
export function countUp(el, to, { from = 0, duration = 1100 } = {}) {
  if (!el) return;
  const target = Math.round(Number(to) || 0);

  if (prefersReducedMotion() || duration <= 0) {
    el.textContent = target;
    return;
  }

  const start = performance.now();
  const step = (now) => {
    const t = Math.min(1, (now - start) / duration);
    const eased = t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
    el.textContent = Math.round(from + (target - from) * eased);
    if (t < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

/**
 * Overall picture: average score, a short verdict, and which muscles need time
 */
export function summarizeReadiness(muscles) {
  if (!muscles || muscles.length === 0) {
    return { score: 0, title: 'No data yet', sub: 'Log your first session to start the scan.' };
  }

  const score = Math.round(muscles.reduce((sum, m) => sum + m.readiness, 0) / muscles.length);
  const recovering = muscles
    .filter(m => m.readiness < 70)
    .sort((a, b) => a.readiness - b.readiness);

  let title;
  if (score >= 90 && recovering.length === 0) title = 'Ready to train';
  else if (score >= 75) title = 'Good to go';
  else if (score >= 55) title = 'Train smart today';
  else title = 'Take a recovery day';

  let sub;
  if (recovering.length === 0) {
    sub = 'Every muscle group has recovered.';
  } else {
    const names = recovering.slice(0, 2).map((m, i) => (i === 0 ? m.display_name : m.display_name.toLowerCase()));
    const extra = recovering.length - names.length;
    const verb = names.length > 1 ? 'are' : 'is';
    sub = `${names.join(' and ')} ${verb} still recovering${extra > 0 ? `, plus ${extra} more` : ''}.`;
  }

  return { score, title, sub };
}