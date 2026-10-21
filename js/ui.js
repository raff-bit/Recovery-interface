/* ============================================================
   RECOVERY — Shared UI Helpers (heat scale, counters, copy)
   ============================================================ */

/**
 * Converte prontidão (0–100) numa cor contínua: coral → âmbar → volt
 */
export function readinessColor(readiness) {
  const r = Math.max(0, Math.min(100, Number(readiness) || 0));
  const hue = 4 + (r / 100) * 70;
  const light = 60 + (r / 100) * 1.5;
  return `hsl(${hue.toFixed(1)} 100% ${light.toFixed(1)}%)`;
}

const prefersReducedMotion = () =>
  window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Anima um número de 'from' até 'to' com easing (easeOutExpo)
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
 * Resume o estado geral: pontuação média, título e músculos mais fatigados
 */
export function summarizeReadiness(muscles) {
  if (!muscles || muscles.length === 0) {
    return { score: 0, title: 'No data', sub: 'Log a session to start tracking.' };
  }

  const score = Math.round(muscles.reduce((sum, m) => sum + m.readiness, 0) / muscles.length);
  const recovering = muscles
    .filter(m => m.readiness < 70)
    .sort((a, b) => a.readiness - b.readiness);

  let title;
  if (score >= 90 && recovering.length === 0) title = 'Ready to train';
  else if (score >= 75) title = 'Good to go';
  else if (score >= 55) title = 'Train smart';
  else title = 'Recovery day';

  let sub;
  if (recovering.length === 0) {
    sub = 'Every muscle group is fully recovered.';
  } else {
    const names = recovering.slice(0, 2).map(m => m.display_name);
    const extra = recovering.length - names.length;
    sub = names.join(' & ') + (extra > 0 ? ` +${extra} more` : '') + ' still recovering.';
  }

  return { score, title, sub };
}
