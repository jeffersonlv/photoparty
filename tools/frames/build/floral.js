const L = require('../lib'); const { rng, f1 } = L;
let gid = 0;
const PAL = [
  { a: '#fff0ee', b: '#f4b8bd', c: '#e58b9b' },   // blush rose
  { a: '#fff6ea', b: '#f9d3ae', c: '#eda27a' },   // peach
  { a: '#ffffff', b: '#f5e1ea', c: '#dcaabf' },   // ivory pink
  { a: '#f6f0ff', b: '#d5c3ee', c: '#a98fd0' },   // lilac
  { a: '#fffdf3', b: '#fbe9a8', c: '#e8c357' },   // butter
];
const LEAF = [['#b9d3b0', '#6f9a6d'], ['#a5c49c', '#5a8560'], ['#cfe0c0', '#86ab80']];

function defs(id, p) {
  return `<radialGradient id="${id}" cx=".5" cy=".5" r=".75"><stop offset="0" stop-color="${p.a}"/><stop offset=".55" stop-color="${p.b}"/><stop offset="1" stop-color="${p.c}"/></radialGradient>`;
}
function petal(R, w, id, rot, stroke) {
  return `<path transform="rotate(${f1(rot)})" d="M0 0 C${f1(-w)} ${f1(-R * .35)} ${f1(-w * .95)} ${f1(-R * .95)} 0 ${f1(-R)} C${f1(w * .95)} ${f1(-R * .95)} ${f1(w)} ${f1(-R * .35)} 0 0z" fill="url(#${id})" stroke="${stroke}" stroke-width="1" stroke-opacity=".5"/>`;
}
// flor: tipo 'rose' (camadas arredondadas), 'daisy' (petalas finas), 'blossom' (5 petalas)
function flower(r, kind, x, y, R, p) {
  const id = 'pg' + (gid++); let s = `<defs>${defs(id, p)}</defs><g transform="translate(${f1(x)} ${f1(y)}) rotate(${f1(r.range(0, 360))})">`;
  const edge = p.c;
  if (kind === 'rose') {
    const rings = [[10, 1, .95], [8, .78, .85], [7, .58, .8], [6, .4, .75], [5, .24, .7]];
    rings.forEach(([n, k, wk], ri) => { for (let i = 0; i < n; i++) s += petal(R * k, R * k * wk * .55, id, i * 360 / n + ri * 18 + r.range(-6, 6), edge); });
    s += `<circle r="${f1(R * .08)}" fill="${p.c}" opacity=".7"/>`;
  } else if (kind === 'daisy') {
    for (let ring = 0; ring < 2; ring++) for (let i = 0; i < 14; i++) s += petal(R * (ring ? .85 : 1), R * .13, id, i * 360 / 14 + ring * 12, edge);
    s += `<circle r="${f1(R * .2)}" fill="#f2c14e"/><circle r="${f1(R * .2)}" fill="none" stroke="#c98d1c" stroke-width="2" opacity=".6"/>`;
    for (let i = 0; i < 14; i++) { const a = r.range(0, 7), d = r.range(0, R * .15); s += `<circle cx="${f1(Math.cos(a) * d)}" cy="${f1(Math.sin(a) * d)}" r="2" fill="#b87a14" opacity=".6"/>`; }
  } else { // blossom
    for (let i = 0; i < 5; i++) s += petal(R, R * .62, id, i * 72, edge);
    for (let i = 0; i < 5; i++) s += `<path transform="rotate(${i * 72})" d="M0 0 L0 ${f1(-R * .6)}" stroke="${p.c}" stroke-width="1.2" opacity=".35"/>`;
    s += `<circle r="${f1(R * .17)}" fill="#f6d36b"/>`;
    for (let i = 0; i < 8; i++) { const a = i * Math.PI / 4; s += `<circle cx="${f1(Math.cos(a) * R * .3)}" cy="${f1(Math.sin(a) * R * .3)}" r="${f1(R * .045)}" fill="#c9852b"/>`; }
  }
  return s + '</g>';
}
function leaf(r, x, y, len, ang) {
  const [c1, c2] = r.pick(LEAF), id = 'lg' + (gid++), w = len * r.range(.26, .38);
  return `<defs><linearGradient id="${id}" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="${c1}"/><stop offset="1" stop-color="${c2}"/></linearGradient></defs><g transform="translate(${f1(x)} ${f1(y)}) rotate(${f1(ang)})"><path d="M0 0 C${f1(len * .25)} ${f1(-w)} ${f1(len * .75)} ${f1(-w * .9)} ${f1(len)} 0 C${f1(len * .75)} ${f1(w * .9)} ${f1(len * .25)} ${f1(w)} 0 0z" fill="url(#${id})" stroke="#4f7852" stroke-opacity=".35" stroke-width="1"/><path d="M0 0 Q${f1(len * .5)} ${f1(-w * .08)} ${f1(len * .95)} 0" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="1.5"/></g>`;
}
// ponto numa bezier cubica
const bez = (p, t) => { const u = 1 - t; return [u * u * u * p[0][0] + 3 * u * u * t * p[1][0] + 3 * u * t * t * p[2][0] + t * t * t * p[3][0], u * u * u * p[0][1] + 3 * u * u * t * p[1][1] + 3 * u * t * t * p[2][1] + t * t * t * p[3][1]]; };
const bezAng = (p, t) => { const a = bez(p, Math.max(0, t - .01)), b = bez(p, Math.min(1, t + .01)); return Math.atan2(b[1] - a[1], b[0] - a[0]) * 180 / Math.PI; };

// cluster no canto superior-esquerdo (0,0), tamanho ~ S. Espelhado fora.
function cluster(seed, S) {
  const r = rng(seed); let back = '', mid = '', front = '';
  const vines = [
    [[0, S * .25], [S * .25, S * .1], [S * .5, S * .35], [S * .98, S * .22]],
    [[S * .25, 0], [S * .1, S * .25], [S * .38, S * .5], [S * .24, S * .98]],
    [[0, 0], [S * .2, S * .25], [S * .4, S * .35], [S * .72, S * .72]],
  ];
  vines.forEach((v, vi) => {
    const q = v.map(p => f1(p[0]) + ' ' + f1(p[1])); back += `<path d="M${q[0]} C${q[1]} ${q[2]} ${q[3]}" fill="none" stroke="#6f9a6d" stroke-width="${vi === 2 ? 3 : 4}" stroke-linecap="round" opacity=".9"/>`;
    const nL = 9;
    for (let i = 1; i <= nL; i++) { const t = i / (nL + 1), pt = bez(v, t), a = bezAng(v, t), side = i % 2 ? 1 : -1; back += leaf(r, pt[0], pt[1], S * r.range(.1, .17), a + side * r.range(35, 65)); }
    // baby's breath
    for (let i = 0; i < 6; i++) { const t = r.range(.3, .98), pt = bez(v, t), a = (bezAng(v, t) + r.pick([-1, 1]) * r.range(40, 80)) * Math.PI / 180, d = r.range(20, 48); const ex = pt[0] + Math.cos(a) * d, ey = pt[1] + Math.sin(a) * d; back += `<path d="M${f1(pt[0])} ${f1(pt[1])} L${f1(ex)} ${f1(ey)}" stroke="#86ab80" stroke-width="1.5"/>`; for (let k = 0; k < 4; k++) back += `<circle cx="${f1(ex + r.range(-7, 7))}" cy="${f1(ey + r.range(-7, 7))}" r="${f1(r.range(2.2, 4.2))}" fill="#fffaf2" stroke="#e6d6c3" stroke-width=".8"/>`; }
  });
  // flores (ordem: grandes atras)
  const spots = [
    ['rose', S * .2, S * .2, S * .2, 0], ['rose', S * .55, S * .12, S * .11, 1], ['blossom', S * .12, S * .56, S * .12, 2], ['daisy', S * .4, S * .42, S * .12, 4],
    ['blossom', S * .76, S * .2, S * .085, 3], ['rose', S * .2, S * .78, S * .075, 2], ['blossom', S * .58, S * .66, S * .07, 0], ['daisy', S * .88, S * .3, S * .05, 4],
  ];
  for (const [k, x, y, R, pi] of spots) mid += flower(r, k, x, y, R, PAL[pi]);
  // brotos
  for (let i = 0; i < 7; i++) { const a = r.range(.1, 1.4), d = r.range(S * .3, S * .85); const bx = Math.cos(a) * d, by = Math.sin(a) * d; const p = r.pick(PAL); const id = 'bd' + gid++; front += `<defs>${defs(id, p)}</defs><circle cx="${f1(bx)}" cy="${f1(by)}" r="${f1(r.range(5, 10))}" fill="url(#${id})" stroke="${p.c}" stroke-opacity=".6"/>`; }
  return back + mid + front;
}
function place(g, x, y, sx, sy, S, seed, rot = 0) {
  return `<g transform="translate(${x} ${y}) scale(${sx} ${sy}) rotate(${rot})" filter="url(#ds)">${cluster(seed, S)}</g>`;
}
const GEOM = {
  portrait: { W: 1080, H: 1350, win: { x: 96, y: 150, w: 888, h: 880 },
    cl: [[0, 0, 1, 1, 470, 11], [1080, 0, -1, 1, 290, 23], [0, 1350, 1, -1, 250, 37, 0], [1080, 1350, -1, -1, 250, 41]],
    t: { name: [540, 1148, 600, 128], date: [540, 1262, 560, 36] } },
  landscape: { W: 1920, H: 1080, win: { x: 330, y: 56, w: 1260, h: 724 },
    cl: [[0, 0, 1, 1, 540, 11], [1920, 0, -1, 1, 400, 23], [0, 1080, 1, -1, 380, 37], [1920, 1080, -1, -1, 400, 41]],
    t: { name: [960, 878, 900, 124], date: [960, 992, 900, 40] } },
};
function defsCommon() { return `<filter id="ds" x="-10%" y="-10%" width="130%" height="130%"><feDropShadow dx="2" dy="4" stdDeviation="4" flood-color="#5a3a3a" flood-opacity=".28"/></filter><filter id="bl30" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="40"/></filter><filter id="bl4"><feGaussianBlur stdDeviation="4"/></filter>`; }
function bgSvg(g) {
  const { W, H } = g, r = rng(W + 3);
  let s = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}"><defs>${defsCommon()}
<radialGradient id="cream" cx=".5" cy=".45" r=".8"><stop offset="0" stop-color="#fffdf8"/><stop offset=".6" stop-color="#fbf1e6"/><stop offset="1" stop-color="#f0dccb"/></radialGradient>
<filter id="paper"><feTurbulence type="fractalNoise" baseFrequency=".8" numOctaves="2" seed="3"/><feColorMatrix values="0 0 0 0 .6  0 0 0 0 .45  0 0 0 0 .35  0 0 0 .05 0"/></filter>
<linearGradient id="gold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f1d896"/><stop offset=".5" stop-color="#c79a45"/><stop offset="1" stop-color="#ecd08a"/></linearGradient></defs>
<rect width="${W}" height="${H}" fill="url(#cream)"/>`;
  const blots = ['#f7cdd0', '#f9dcc2', '#e7dcf3', '#f8e5b8'];
  for (let i = 0; i < 9; i++) s += `<ellipse cx="${f1(r.range(0, W))}" cy="${f1(r.range(0, H))}" rx="${f1(r.range(120, 320))}" ry="${f1(r.range(90, 240))}" fill="${r.pick(blots)}" opacity="${f1(r.range(.25, .5))}" filter="url(#bl30)"/>`;
  s += `<rect width="${W}" height="${H}" filter="url(#paper)"/>`;
  // thin double border
  s += `<rect x="26" y="26" width="${W - 52}" height="${H - 52}" fill="none" stroke="#caa35a" stroke-opacity=".7" stroke-width="2"/><rect x="38" y="38" width="${W - 76}" height="${H - 76}" fill="none" stroke="#caa35a" stroke-opacity=".45" stroke-width="1"/>`;
  // delicate scattered petals
  for (let i = 0; i < 26; i++) { const p = r.pick(PAL), x = r.range(60, W - 60), y = r.range(60, H - 60); s += `<ellipse cx="${f1(x)}" cy="${f1(y)}" rx="${f1(r.range(5, 9))}" ry="${f1(r.range(3, 5))}" transform="rotate(${f1(r.range(0, 180))} ${f1(x)} ${f1(y)})" fill="${p.b}" opacity="${f1(r.range(.35, .65))}"/>`; }
  // mat/shadow under window region (hidden by photo, aids edges)
  return s + '</svg>';
}
function ovSvg(g) {
  const { W, H, win } = g;
  let s = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}"><defs>${defsCommon()}
<linearGradient id="gold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f6e3a8"/><stop offset=".5" stop-color="#c09038"/><stop offset="1" stop-color="#f0d68f"/></linearGradient>
<mask id="nohole"><rect width="${W}" height="${H}" fill="#fff"/><rect x="${win.x}" y="${win.y}" width="${win.w}" height="${win.h}" fill="#000"/></mask></defs>`;
  const ring = (d) => `M${win.x - d} ${win.y - d} h${win.w + 2 * d} v${win.h + 2 * d} h-${win.w + 2 * d}z M${win.x} ${win.y} v${win.h} h${win.w} v-${win.h}z`;
  s += `<g mask="url(#nohole)"><rect x="${win.x - 6}" y="${win.y - 4}" width="${win.w + 12}" height="${win.h + 12}" fill="#5a3a3a" opacity=".3" filter="url(#bl4)"/>`;
  s += `<path d="${ring(12)}" fill="#fffaf0" fill-rule="evenodd"/><path d="${ring(12)}" fill="none"/>`;
  s += `<path d="${ring(12)}" fill="url(#gold)" fill-rule="evenodd" opacity=".0"/>`;
  s += `<path d="M${win.x - 12} ${win.y - 12} h${win.w + 24} v${win.h + 24} h-${win.w + 24}z M${win.x - 8} ${win.y - 8} v${win.h + 16} h${win.w + 16} v-${win.h + 16}z" fill="url(#gold)" fill-rule="evenodd"/>`;
  s += `<path d="M${win.x - 3} ${win.y - 3} h${win.w + 6} v${win.h + 6} h-${win.w + 6}z M${win.x} ${win.y} v${win.h} h${win.w} v-${win.h}z" fill="url(#gold)" fill-rule="evenodd" opacity=".8"/></g>`;
  for (const [x, y, sx, sy, S, seed] of g.cl) s += place(g, x, y, sx, sy, S, seed);
  return s + '</svg>';
}
async function build(outDir) {
  const res = {};
  for (const [o, g] of Object.entries(GEOM)) {
    const bg = await L.svgToBuf(bgSvg(g)), ov = await L.svgToBuf(ovSvg(g));
    res[o] = [await L.savePng(bg, `${outDir}/${o}-bg.png`, { colors: 128, dither: .3 }), await L.savePng(ov, `${outDir}/${o}-overlay.png`, { colors: 200, dither: .5 })];
  }
  return res;
}
module.exports = { build, GEOM };
if (require.main === module) build(L.FR + '/floral').then(r => console.log(JSON.stringify(r)));
