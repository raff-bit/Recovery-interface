/* ============================================================
   RECOVERY — Procedural Body Map
   Draws the four bodies (front/back, male/female) as SVG.

   How it works:
   - Every shape is described ONCE, for the left half of the body,
     as a list of points in a 240 x 480 box (x = 120 is the centre).
   - The right half is the same list mirrored across x = 120.
   - Points are joined with smooth curves (Catmull-Rom splines).
     A point marked 'c' stays a sharp corner.
   - The female body reuses the same points, reshaped: narrower
     shoulders and waist, wider hips.
   ============================================================ */

const SVG_NS = 'http://www.w3.org/2000/svg';
const CX = 120;
const VIEW_W = 240;
const VIEW_H = 480;

const LABELS = {
  upper_chest: 'Upper chest', lower_chest: 'Lower chest',
  front_delts: 'Front delts', side_delts: 'Side delts', rear_delts: 'Rear delts',
  biceps: 'Biceps', triceps: 'Triceps', forearms: 'Forearms',
  traps: 'Traps', lats: 'Lats', lower_back: 'Lower back',
  abs: 'Abs', obliques: 'Obliques',
  quads: 'Quads', hamstrings: 'Hamstrings', glutes: 'Glutes', calves: 'Calves',
};

/* ---------- OUTLINE (left half, top of head -> crotch) ----------
   Flags: 'a' = arm point, 'l' = leg point, 'c' = sharp corner */
const SILHOUETTE = [
  [120, 12], [108, 15], [100, 25], [97, 39], [99, 52], [104, 61],
  [109, 67], [109, 76],
  [100, 85], [88, 90],
  [74, 94, 'a'], [63, 102, 'a'], [57, 116, 'a'],
  [55, 134, 'a'], [52, 156, 'a'], [50, 176, 'a'],
  [47, 194, 'a'], [44, 216, 'a'], [42, 238, 'a'],
  [39, 252, 'a'], [36, 268, 'a'], [38, 282, 'a'], [44, 288, 'a'], [50, 284, 'a'], [53, 270, 'a'], [54, 256, 'a'],
  [55, 244, 'a'],
  [58, 222, 'a'], [62, 200, 'a'], [66, 184, 'a'],
  [69, 164, 'a'], [73, 146, 'a'], [78, 132, 'ac'],
  [81, 146], [85, 170], [89, 192],
  [88, 212], [84, 232],
  [81, 252, 'l'], [80, 278, 'l'], [84, 308, 'l'], [90, 336, 'l'],
  [89, 350, 'l'], [86, 372, 'l'], [87, 394, 'l'], [93, 420, 'l'], [96, 442, 'l'],
  [94, 454, 'l'], [91, 465, 'l'], [98, 471, 'l'], [111, 471, 'l'], [114, 463, 'l'], [112, 452, 'l'],
  [110, 442, 'l'],
  [110, 418, 'l'], [112, 392, 'l'], [111, 366, 'l'], [109, 348, 'l'],
  [111, 322, 'l'], [114, 296, 'l'], [117, 274, 'l'],
  [120, 262, 'c'],
];

/* Rounded rectangle as points (used for the ab segments) */
function rr(x1, y1, x2, y2, r = 3) {
  return [
    [x1 + r, y1], [x2 - r, y1], [x2, y1 + r], [x2, y2 - r],
    [x2 - r, y2], [x1 + r, y2], [x1, y2 - r], [x1, y1 + r],
  ];
}

/* ---------- FRONT ----------
   part: which way the shape moves on the female body
   mirror: false -> the shape already spans both halves */
const FRONT_TISSUE = [
  { part: 'arm',   pts: [[57, 140], [59, 152], [58, 168], [56, 180], [52, 174], [54, 156]] },
  { part: 'torso', pts: [[82, 134], [87, 140], [88, 148], [84, 150], [81, 144]] },
  { part: 'leg',   pts: [[108, 262], [116, 274], [113, 290], [108.5, 292], [107.5, 278]] },
  { part: 'leg',   pts: [[100, 336], [106, 340], [106, 347], [100, 351], [94, 347], [94, 340]] },
  { part: 'leg',   pts: [[93.5, 356], [99, 356], [99.5, 390], [98.5, 428], [95.5, 430], [94, 402], [93.5, 376]] },
];

const FRONT_MUSCLES = [
  { key: 'traps',       part: 'torso', pts: [[108, 74], [108, 83], [101, 87], [89, 91], [97, 84]] },
  { key: 'side_delts',  part: 'arm',   pts: [[73, 95], [63, 103], [57, 117], [56, 132], [61, 142], [63, 127], [65, 112], [70, 101]] },
  { key: 'front_delts', part: 'arm',   pts: [[76, 95], [89, 92], [92, 96], [86, 106], [79, 120], [70, 137], [65, 142], [67, 126], [69, 111], [73, 100]] },
  { key: 'upper_chest', part: 'torso', pts: [[118.5, 95, 'c'], [118.5, 116, 'c'], [103, 116], [91, 118], [83, 121, 'c'], [86, 110], [93, 99], [105, 95]] },
  { key: 'lower_chest', part: 'torso', pts: [[118.5, 119, 'c'], [118.5, 146, 'c'], [108, 150], [96, 147], [88, 138], [83, 125, 'c'], [93, 121], [105, 119]] },
  { key: 'biceps',      part: 'arm',   pts: [[70, 141], [64, 147], [60, 158], [59, 172], [61, 182], [66, 180], [69, 166], [72, 151]] },
  { key: 'forearms',    part: 'arm',   pts: [[50, 186], [57, 186], [54, 200], [50, 218], [46, 234], [44, 220], [46, 200]] },
  { key: 'forearms',    part: 'arm',   pts: [[59, 187], [65, 188], [62, 204], [58, 222], [55, 238], [49, 238], [52, 222], [56, 204]] },
  { key: 'abs',         part: 'torso', pts: rr(106, 151, 118.5, 168) },
  { key: 'abs',         part: 'torso', pts: rr(106, 171, 118.5, 189) },
  { key: 'abs',         part: 'torso', pts: rr(106.5, 192, 118.5, 210) },
  { key: 'abs',         part: 'torso', pts: [[108, 213], [118.5, 213, 'c'], [118.5, 247, 'c'], [114, 247], [109, 234], [107, 220]] },
  { key: 'obliques',    part: 'torso', pts: [[86, 150], [96, 153], [102, 161], [103.5, 180], [103.5, 200], [102, 216], [98, 230], [93, 225], [90, 206], [88, 184], [85, 165]] },
  { key: 'quads',       part: 'leg',   pts: [[82, 258], [89, 262], [93, 282], [94, 304], [92, 324], [89, 333], [85, 326], [82, 306], [80.5, 282]] },
  { key: 'quads',       part: 'leg',   pts: [[96, 257], [104, 256], [107, 272], [107, 296], [103, 318], [99, 331], [95.5, 316], [94.5, 290], [95, 270]] },
  { key: 'quads',       part: 'leg',   pts: [[108, 292], [112, 300], [112.5, 318], [108, 333], [102, 337], [101, 330], [104, 316], [106, 302]] },
  { key: 'calves',      part: 'leg',   pts: [[88, 356], [92, 358], [92.5, 380], [91, 398], [87.5, 392], [86.5, 372]] },
  { key: 'calves',      part: 'leg',   pts: [[101, 352], [108, 355], [111, 376], [108.5, 402], [103, 405], [100, 384], [100, 364]] },
];

/* ---------- BACK ---------- */
const BACK_TISSUE = [
  { part: 'torso', pts: [[86, 102], [95, 106], [103, 116], [106, 128], [96, 133], [84, 129], [80, 117], [81, 106]] },
  { part: 'torso', pts: [[88, 205], [100, 203], [112, 209], [104, 215], [92, 219], [86, 213]] },
  { part: 'leg',   pts: [[92, 399], [107, 401], [105, 426], [98, 429], [93, 414]] },
];

function mirrorPts(pts) {
  return pts.map(([x, y, f]) => [2 * CX - x, y, f]);
}

/* Joins a left half (from the centre line back to the centre line) with its mirror */
function sym(left) {
  return [...left, ...mirrorPts(left.slice(1, -1)).reverse()];
}

const TRAPS_BACK_LEFT = [
  [120, 70, 'c'], [110, 73], [106, 82], [96, 87], [82, 94], [90, 100],
  [106, 114], [113, 134], [117, 150], [120, 160, 'c'],
];

const BACK_MUSCLES = [
  { key: 'lats',       part: 'torso', pts: [[80, 130], [92, 134], [103, 140], [110, 152], [110, 172], [106, 194], [100, 205], [94, 206], [90, 190], [86, 166], [82, 146]] },
  { key: 'lower_back', part: 'torso', pts: [[111.5, 168], [118.5, 170, 'c'], [118.5, 232, 'c'], [110, 232], [107, 210], [109, 186]] },
  { key: 'traps',      part: 'torso', mirror: false, left: TRAPS_BACK_LEFT },
  { key: 'side_delts', part: 'arm',   pts: [[71, 97], [63, 103], [57, 117], [56, 132], [61, 142], [63, 126], [65, 112], [68, 101]] },
  { key: 'rear_delts', part: 'arm',   pts: [[74, 97], [86, 96], [83, 104], [76, 116], [68, 134], [64, 142], [65, 126], [67, 111], [70, 100]] },
  { key: 'triceps',    part: 'arm',   pts: [[59, 139], [64, 139], [64, 158], [60, 176], [56, 166], [55, 150]] },
  { key: 'triceps',    part: 'arm',   pts: [[67, 138], [72, 147], [71, 166], [66, 180], [62, 178], [66, 160]] },
  { key: 'forearms',   part: 'arm',   pts: [[50, 186], [57, 186], [54, 202], [50, 220], [46, 234], [44, 220], [46, 200]] },
  { key: 'forearms',   part: 'arm',   pts: [[59, 187], [65, 188], [62, 204], [58, 222], [55, 238], [49, 238], [52, 222], [56, 204]] },
  { key: 'glutes',     part: 'torso', pts: [[89, 219], [104, 215], [118.5, 222, 'c'], [118.5, 254, 'c'], [108, 260], [96, 258], [88, 248], [86, 232]] },
  { key: 'hamstrings', part: 'leg',   pts: [[83, 264], [97, 264], [99, 292], [97, 324], [92, 335], [87, 327], [82.5, 296]] },
  { key: 'hamstrings', part: 'leg',   pts: [[100, 264], [113.5, 270], [113, 298], [108, 326], [102, 336], [99, 324], [101, 294]] },
  { key: 'calves',     part: 'leg',   pts: [[86.5, 350], [98, 352], [100, 375], [97, 402], [90, 399], [86, 374]] },
  { key: 'calves',     part: 'leg',   pts: [[101, 350], [110, 352], [112, 374], [108, 404], [102, 402], [100, 376]] },
];

/* ---------- FEMALE RESHAPING ----------
   Horizontal scale by height (y): narrower shoulders and waist, wider hips. */
const TORSO_SCALE = [[0, 0.92], [70, 0.92], [88, 0.87], [150, 0.87], [196, 0.83], [232, 1.06], [262, 1.1], [480, 1.1]];
const LEG_SCALE = [[0, 1.1], [262, 1.1], [340, 1.0], [480, 0.97]];
const ARM_SHIFT = (CX - 58) * (1 - 0.87);

function lookup(table, y) {
  for (let i = 1; i < table.length; i++) {
    const [y0, v0] = table[i - 1];
    const [y1, v1] = table[i];
    if (y <= y1) return v0 + (v1 - v0) * ((y - y0) / (y1 - y0));
  }
  return table[table.length - 1][1];
}

function reshapePoint([x, y, flags = ''], part) {
  const kind = flags.includes('a') ? 'arm' : flags.includes('l') ? 'leg' : part;
  if (kind === 'arm') return [x + ARM_SHIFT, y, flags];
  const scale = lookup(kind === 'leg' ? LEG_SCALE : TORSO_SCALE, y);
  return [CX + (x - CX) * scale, y, flags];
}

/* ---------- SMOOTH PATHS ---------- */
function toPath(pts) {
  const n = pts.length;
  const f = (v) => Math.round(v * 10) / 10;
  const isCorner = (p) => (p[2] || '').includes('c');
  let d = `M${f(pts[0][0])} ${f(pts[0][1])}`;

  for (let i = 0; i < n; i++) {
    const p0 = pts[(i - 1 + n) % n];
    const p1 = pts[i];
    const p2 = pts[(i + 1) % n];
    const p3 = pts[(i + 2) % n];
    const c1 = isCorner(p1) ? p1 : [p1[0] + (p2[0] - p0[0]) / 6, p1[1] + (p2[1] - p0[1]) / 6];
    const c2 = isCorner(p2) ? p2 : [p2[0] - (p3[0] - p1[0]) / 6, p2[1] - (p3[1] - p1[1]) / 6];
    d += `C${f(c1[0])} ${f(c1[1])} ${f(c2[0])} ${f(c2[1])} ${f(p2[0])} ${f(p2[1])}`;
  }
  return d + 'Z';
}

/* Turns one shape definition into one or two SVG path strings */
function shapePaths(shape, female) {
  const fix = (pts) => (female ? pts.map((p) => reshapePoint(p, shape.part)) : pts);
  if (shape.left) return [toPath(sym(fix(shape.left)))];
  const left = fix(shape.pts);
  return shape.mirror === false ? [toPath(left)] : [toPath(left), toPath(mirrorPts(left))];
}

function silhouettePath(female) {
  const left = female ? SILHOUETTE.map((p) => reshapePoint(p, 'torso')) : SILHOUETTE;
  return toPath(sym(left));
}

/* ---------- SVG BUILDING ---------- */
function el(name, attrs = {}) {
  const node = document.createElementNS(SVG_NS, name);
  Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, v));
  return node;
}

function buildDefs(uid, outline) {
  const defs = el('defs');

  const clip = el('clipPath', { id: `clip-${uid}` });
  clip.appendChild(el('path', { d: outline }));
  defs.appendChild(clip);

  const base = el('linearGradient', { id: `base-${uid}`, x1: '0', y1: '0', x2: '0', y2: '1' });
  base.appendChild(el('stop', { offset: '0', 'stop-color': '#1d2458' }));
  base.appendChild(el('stop', { offset: '1', 'stop-color': '#121840' }));
  defs.appendChild(base);

  const shade = el('radialGradient', { id: `shade-${uid}`, cx: '0.4', cy: '0.22', r: '0.75' });
  shade.appendChild(el('stop', { offset: '0', 'stop-color': '#ffffff', 'stop-opacity': '0.07' }));
  shade.appendChild(el('stop', { offset: '0.6', 'stop-color': '#ffffff', 'stop-opacity': '0' }));
  shade.appendChild(el('stop', { offset: '1', 'stop-color': '#000010', 'stop-opacity': '0.25' }));
  defs.appendChild(shade);

  const lines = el('pattern', { id: `lines-${uid}`, width: '4', height: '3', patternUnits: 'userSpaceOnUse' });
  lines.appendChild(el('rect', { width: '4', height: '0.7', fill: '#c9d2ff', 'fill-opacity': '0.07' }));
  defs.appendChild(lines);

  const beam = el('linearGradient', { id: `beam-${uid}`, x1: '0', y1: '0', x2: '0', y2: '1' });
  beam.appendChild(el('stop', { offset: '0', 'stop-color': '#c9d2ff', 'stop-opacity': '0' }));
  beam.appendChild(el('stop', { offset: '0.85', 'stop-color': '#c9d2ff', 'stop-opacity': '0.14' }));
  beam.appendChild(el('stop', { offset: '0.98', 'stop-color': '#e6eaff', 'stop-opacity': '0.55' }));
  beam.appendChild(el('stop', { offset: '1', 'stop-color': '#ffffff', 'stop-opacity': '0.95' }));
  defs.appendChild(beam);

  // Fine light edge around the whole silhouette (hologram rim)
  const edge = el('filter', { id: `edge-${uid}`, x: '-5%', y: '-5%', width: '110%', height: '110%' });
  edge.appendChild(el('feMorphology', { in: 'SourceAlpha', operator: 'dilate', radius: '1', result: 'grown' }));
  edge.appendChild(el('feFlood', { 'flood-color': '#aab6ff', 'flood-opacity': '0.55' }));
  edge.appendChild(el('feComposite', { in2: 'grown', operator: 'in', result: 'rim' }));
  const merge = el('feMerge');
  merge.appendChild(el('feMergeNode', { in: 'rim' }));
  merge.appendChild(el('feMergeNode', { in: 'SourceGraphic' }));
  edge.appendChild(merge);
  defs.appendChild(edge);

  return defs;
}

function buildFigure({ id, side, female }) {
  const uid = id;
  const outline = silhouettePath(female);
  const svg = el('svg', {
    id,
    class: `hologram-svg hologram-face ${side}`,
    viewBox: `0 0 ${VIEW_W} ${VIEW_H}`,
    role: 'img',
    'aria-label': `${side === 'front' ? 'Front' : 'Back'} view of your muscles, coloured by readiness`,
  });

  svg.appendChild(buildDefs(uid, outline));

  // Projector pad under the feet
  const pad = el('g', { class: 'body-pad', 'aria-hidden': 'true' });
  pad.appendChild(el('ellipse', { cx: CX, cy: 470, rx: 74, ry: 9 }));
  pad.appendChild(el('ellipse', { cx: CX, cy: 470, rx: 46, ry: 5.5 }));
  svg.appendChild(pad);

  svg.appendChild(el('path', { class: 'body-base', d: outline, fill: `url(#base-${uid})`, filter: `url(#edge-${uid})` }));

  const tissue = el('g', { class: 'body-tissue', 'aria-hidden': 'true' });
  (side === 'front' ? FRONT_TISSUE : BACK_TISSUE).forEach((shape) => {
    shapePaths(shape, female).forEach((d) => tissue.appendChild(el('path', { d })));
  });
  svg.appendChild(tissue);

  const muscles = el('g', { class: 'body-muscles' });
  (side === 'front' ? FRONT_MUSCLES : BACK_MUSCLES).forEach((shape) => {
    shapePaths(shape, female).forEach((d) => {
      muscles.appendChild(el('path', {
        class: 'muscle-shape',
        'data-muscle': shape.key,
        'aria-label': LABELS[shape.key] || shape.key,
        d,
      }));
    });
  });
  svg.appendChild(muscles);

  const overlay = el('g', { class: 'body-overlay', 'clip-path': `url(#clip-${uid})`, 'aria-hidden': 'true' });
  overlay.appendChild(el('rect', { class: 'body-shade', width: VIEW_W, height: VIEW_H, fill: `url(#shade-${uid})` }));
  overlay.appendChild(el('rect', { class: 'body-lines', width: VIEW_W, height: VIEW_H, fill: `url(#lines-${uid})` }));
  overlay.appendChild(el('rect', { class: 'scan-beam', x: 0, y: -80, width: VIEW_W, height: 80, fill: `url(#beam-${uid})` }));
  svg.appendChild(overlay);

  return svg;
}

/**
 * Fills the rotator with the four bodies. Only the pair matching the
 * profile's sex is shown (hologram.js switches them).
 */
export function buildBodyMap(container) {
  if (!container || container.dataset.built) return;
  [
    { id: 'body-front',        side: 'front', female: false },
    { id: 'body-back',         side: 'back',  female: false },
    { id: 'body-front-female', side: 'front', female: true },
    { id: 'body-back-female',  side: 'back',  female: true },
  ].forEach((cfg) => {
    const svg = buildFigure(cfg);
    if (cfg.female) svg.style.display = 'none';
    container.appendChild(svg);
  });
  container.dataset.built = 'true';
}