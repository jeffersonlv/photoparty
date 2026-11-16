const L = require('../lib'); const { rng, f1 } = L;

function tornPath(r, x0, y0, x1, y1, amp, step) {
  const pts = [];
  const edge = (ax, ay, bx, by) => {
    const len = Math.hypot(bx - ax, by - ay), n = Math.floor(len / step);
    const nx = -(by - ay) / len, ny = (bx - ax) / len; let off = 0;
    for (let i = 0; i < n; i++) {
      const t = i / n; off += r.range(-amp * .5, amp * .5); off *= .8; if (r() < .03) off += r.range(-amp * 2, amp * 2);
      pts.push([ax + (bx - ax) * t + nx * off, ay + (by - ay) * t + ny * off]);
    }
  };
  edge(x0, y0, x1, y0); edge(x1, y0, x1, y1); edge(x1, y1, x0, y1); edge(x0, y1, x0, y0);
  return pts;
}
const poly = (pts) => 'M' + pts.map(p => `${f1(p[0])} ${f1(p[1])}`).join('L') + 'z';

function nail(x, y, s = 1) {
  return `<g transform="translate(${x} ${y}) scale(${s})"><ellipse cx="3" cy="6" rx="22" ry="9" fill="#000" opacity=".45" filter="url(#bl3)"/><circle r="17" fill="url(#nailg)"/><circle r="17" fill="none" stroke="#2a1d10" stroke-width="2"/><circle r="9" fill="url(#nailg2)"/><ellipse cx="-5" cy="-6" rx="5" ry="3" fill="#fff" opacity=".7"/></g>`;
}
function bg(W, H, P) {
  const r = rng(W + 31);
  const pts = tornPath(r, P.x, P.y, P.x + P.w, P.y + P.h, 7, 14);
  const pts2 = pts.map(p => [p[0] + r.range(-3, 3), p[1] + r.range(-3, 3)]);
  let s = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}"><defs>
<filter id="bl3" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="3"/></filter>
<filter id="bl12" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="14"/></filter>
<filter id="wood" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency=".004 .1" numOctaves="2" seed="3"/><feColorMatrix values="0 0 0 0 .05  0 0 0 0 .03  0 0 0 0 .01  0 0 0 1.5 -.35"/></filter>
<filter id="paper" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency=".03" numOctaves="3" seed="7"/><feColorMatrix values="0 0 0 0 .42  0 0 0 0 .28  0 0 0 0 .1  0 0 0 1.1 -.35"/></filter>
<filter id="fiber" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency=".6 .02" numOctaves="2" seed="11"/><feColorMatrix values="0 0 0 0 .3  0 0 0 0 .2  0 0 0 0 .08  0 0 0 .9 -.35"/></filter>
<linearGradient id="pg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ecd9a8"/><stop offset=".5" stop-color="#dcc088"/><stop offset="1" stop-color="#c9a468"/></linearGradient>
<radialGradient id="burn" cx=".5" cy=".5" r=".72"><stop offset=".55" stop-color="#6b3d12" stop-opacity="0"/><stop offset=".85" stop-color="#6b3d12" stop-opacity=".28"/><stop offset="1" stop-color="#3d1f05" stop-opacity=".6"/></radialGradient>
<radialGradient id="nailg" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#d6d0c4"/><stop offset=".5" stop-color="#6c675e"/><stop offset="1" stop-color="#26231e"/></radialGradient>
<radialGradient id="nailg2" cx=".4" cy=".35" r=".7"><stop offset="0" stop-color="#b9b3a6"/><stop offset="1" stop-color="#3c3830"/></radialGradient>
<clipPath id="pc"><path d="${poly(pts)}"/></clipPath>
<linearGradient id="plank" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5a3a22"/><stop offset=".5" stop-color="#4a2f1b"/><stop offset="1" stop-color="#3a2313"/></linearGradient>
<radialGradient id="vig" cx=".5" cy=".5" r=".75"><stop offset=".5" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".6"/></radialGradient>
</defs>`;
  // wood wall
  const ph = 150; s += `<rect width="${W}" height="${H}" fill="#4a2f1b"/>`;
  for (let y = 0, i = 0; y < H; y += ph, i++) {
    const h = ph + r.range(-12, 12); s += `<rect x="0" y="${y}" width="${W}" height="${h}" fill="url(#plank)" opacity="${f1(r.range(.75, 1))}" transform=""/>`;
    s += `<rect x="0" y="${y}" width="${W}" height="3" fill="#150a03" opacity=".85"/><rect x="0" y="${y + 3}" width="${W}" height="2" fill="#fff" opacity=".06"/>`;
    // knots
    if (r() < .5) { const kx = r.range(0, W), ky = y + r.range(30, 120); s += `<ellipse cx="${f1(kx)}" cy="${f1(ky)}" rx="${f1(r.range(14, 28))}" ry="${f1(r.range(8, 14))}" fill="#22120a" opacity=".6"/><ellipse cx="${f1(kx)}" cy="${f1(ky)}" rx="${f1(r.range(24, 46))}" ry="${f1(r.range(14, 24))}" fill="none" stroke="#2a170c" stroke-width="2" opacity=".5"/>`; }
    if (r() < .6) { const sx = r.range(120, W - 120); s += `<rect x="${f1(sx)}" y="${y}" width="2" height="${f1(h)}" fill="#150a03" opacity=".7"/>`; }
  }
  s += `<rect width="${W}" height="${H}" filter="url(#wood)" opacity=".9"/>`;
  s += `<rect width="${W}" height="${H}" fill="url(#vig)"/>`;
  // paper shadow
  s += `<path d="${poly(pts)}" fill="#000" opacity=".65" filter="url(#bl12)" transform="translate(10,16)"/>`;
  // torn fibrous border: lighter, slightly larger
  s += `<path d="${poly(pts2)}" fill="#f4e8c8" transform="translate(-1 -1)"/>`;
  // paper body
  s += `<path d="${poly(pts)}" fill="url(#pg)"/>`;
  s += `<g clip-path="url(#pc)">`;
  s += `<rect x="${P.x - 20}" y="${P.y - 20}" width="${P.w + 40}" height="${P.h + 40}" filter="url(#paper)" opacity=".6" style="mix-blend-mode:multiply"/>`;
  s += `<rect x="${P.x - 20}" y="${P.y - 20}" width="${P.w + 40}" height="${P.h + 40}" filter="url(#fiber)" opacity=".18"/>`;
  // stains
  for (let i = 0; i < 9; i++) { const cx = r.range(P.x, P.x + P.w), cy = r.range(P.y, P.y + P.h), rr = r.range(40, 160); s += `<ellipse cx="${f1(cx)}" cy="${f1(cy)}" rx="${f1(rr)}" ry="${f1(rr * r.range(.5, 1))}" fill="#7a4a14" opacity="${f1(r.range(.05, .14))}" filter="url(#bl12)"/>`; if (r() < .5) s += `<ellipse cx="${f1(cx)}" cy="${f1(cy)}" rx="${f1(rr * .7)}" ry="${f1(rr * .5)}" fill="none" stroke="#6b3d10" stroke-width="3" opacity=".12" filter="url(#bl3)"/>`; }
  // coffee ring
  const rx = P.x + P.w * .82, ry = P.y + P.h * .86; s += `<circle cx="${f1(rx)}" cy="${f1(ry)}" r="52" fill="none" stroke="#5a3210" stroke-width="7" opacity=".16" filter="url(#bl3)"/>`;
  // creases
  const cx1 = P.x + P.w / 2, cy1 = P.y + P.h / 2;
  s += `<rect x="${cx1 - 1}" y="${P.y}" width="2" height="${P.h}" fill="#3d2105" opacity=".18"/><rect x="${cx1 + 1}" y="${P.y}" width="2" height="${P.h}" fill="#fff" opacity=".22"/>`;
  s += `<rect x="${P.x}" y="${cy1 - 1}" width="${P.w}" height="2" fill="#3d2105" opacity=".18"/><rect x="${P.x}" y="${cy1 + 1}" width="${P.w}" height="2" fill="#fff" opacity=".22"/>`;
  s += `<rect x="${P.x}" y="${P.y}" width="${P.w}" height="${P.h}" fill="url(#burn)"/>`;
  s += `</g>`;
  return { s, pts };
}
function ornaments(P, lay) {
  const ink = '#3b2412'; let s = '';
  const rule = (x1, x2, y) => `<rect x="${x1}" y="${y}" width="${x2 - x1}" height="5" fill="${ink}"/><rect x="${x1}" y="${y + 9}" width="${x2 - x1}" height="2" fill="${ink}"/>`;
  const star = (cx, cy, R) => { let d = ''; for (let i = 0; i < 10; i++) { const a = -Math.PI / 2 + i * Math.PI / 5, rr = i % 2 ? R * .42 : R; d += (i ? 'L' : 'M') + f1(cx + rr * Math.cos(a)) + ' ' + f1(cy + rr * Math.sin(a)); } return `<path d="${d}z" fill="${ink}"/>`; };
  const diamond = (cx, cy, R) => `<path d="M${cx - R} ${cy} L${cx} ${cy - R * .6} L${cx + R} ${cy} L${cx} ${cy + R * .6}z" fill="${ink}"/>`;
  const m = lay.margin;
  s += `<rect x="${P.x + m}" y="${P.y + m}" width="${P.w - 2 * m}" height="${P.h - 2 * m}" fill="none" stroke="${ink}" stroke-width="5" opacity=".9"/><rect x="${P.x + m + 12}" y="${P.y + m + 12}" width="${P.w - 2 * m - 24}" height="${P.h - 2 * m - 24}" fill="none" stroke="${ink}" stroke-width="1.8" opacity=".85"/>`;
  for (const [cx, cy] of [[P.x + m + 6, P.y + m + 6], [P.x + P.w - m - 6, P.y + m + 6], [P.x + m + 6, P.y + P.h - m - 6], [P.x + P.w - m - 6, P.y + P.h - m - 6]]) s += star(cx, cy, 14);
  for (const l of lay.rules) { const cx = (l.x1 + l.x2) / 2; s += rule(l.x1, l.x2, l.y); s += `<rect x="${cx - 40}" y="${l.y - 7}" width="80" height="25" fill="#dcc088"/>` + star(cx - 28, l.y + 5.5, 9) + star(cx, l.y + 5.5, 12) + star(cx + 28, l.y + 5.5, 9); }
  for (const d of lay.diamonds) s += `<rect x="${d.x1}" y="${d.y - 1.5}" width="${d.x2 - d.x1}" height="3" fill="${ink}"/>` + diamond((d.x1 + d.x2) / 2, d.y, 16);
  return s;
}
function overlay(W, H, win) {
  const b = 26, x = win.x - b, y = win.y - b, w = win.w + 2 * b, h = win.h + 2 * b;
  const hole = `M${win.x} ${win.y} v${win.h} h${win.w} v-${win.h}z`;
  const ring = (a, c, d, e) => `M${a} ${c} h${d} v${e} h-${d} z`;
  let s = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}"><defs>
<mask id="nohole"><rect width="${W}" height="${H}" fill="#fff"/><rect x="${win.x}" y="${win.y}" width="${win.w}" height="${win.h}" fill="#000"/></mask>
<filter id="bl6" x="-10%" y="-10%" width="120%" height="120%"><feGaussianBlur stdDeviation="6"/></filter>
<filter id="rough"><feTurbulence type="fractalNoise" baseFrequency=".5" numOctaves="2" seed="2" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="3"/></filter>
<filter id="ink" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency=".7" numOctaves="2" seed="5"/><feColorMatrix values="0 0 0 0 .86  0 0 0 0 .72  0 0 0 0 .45  0 0 0 1.2 -.3"/></filter>
</defs><g mask="url(#nohole)">`;
  // shadow cast by photo onto paper (outside window)
  s += `<rect x="${win.x}" y="${win.y}" width="${win.w}" height="${win.h}" fill="#2a1505" opacity=".55" filter="url(#bl6)" transform="translate(0,4)"/>`;
  // outer border (ink) 3 bands
  s += `<g filter="url(#rough)"><path d="${ring(x, y, w, h)} ${hole}" fill="#3b2412" fill-rule="evenodd"/>`;
  s += `<path d="${ring(x + 7, y + 7, w - 14, h - 14)} ${ring(x + 12, y + 12, w - 24, h - 24)}" fill="#dcc088" fill-rule="evenodd"/>`;
  s += `<path d="${ring(x + 12, y + 12, w - 24, h - 24)} ${hole}" fill="#3b2412" fill-rule="evenodd"/></g>`;
  s += `<path d="${ring(win.x - 8, win.y - 8, win.w + 16, win.h + 16)} ${hole}" fill="#3b2412" fill-rule="evenodd" opacity=".9"/>`;
  // corner ornaments (squares with dots)
  for (const [cx, cy] of [[x, y], [x + w, y], [x, y + h], [x + w, y + h]]) s += `<circle cx="${cx}" cy="${cy}" r="14" fill="#3b2412"/><circle cx="${cx}" cy="${cy}" r="8" fill="#dcc088"/><circle cx="${cx}" cy="${cy}" r="3.5" fill="#3b2412"/>`;
  // worn spots on border (paper color specks)
  const r = rng(5); for (let i = 0; i < 90; i++) { const side = r.int(0, 3); let px, py; if (side < 2) { px = x + r.range(0, w); py = side ? y + h - r.range(0, 14) : y + r.range(0, 14); } else { py = y + r.range(0, h); px = side == 2 ? x + r.range(0, 14) : x + w - r.range(0, 14); } s += `<circle cx="${f1(px)}" cy="${f1(py)}" r="${f1(r.range(.8, 2.4))}" fill="#dcc088" opacity=".8"/>`; }
  s += `</g></svg>`;
  return s;
}
const GEOM = {
  portrait: { W: 1080, H: 1350, P: { x: 62, y: 44, w: 956, h: 1262 }, win: { x: 170, y: 322, w: 740, h: 620 }, margin: 34,
    rules: [{ x1: 130, x2: 950, y: 266 }], diamonds: [{ x1: 170, x2: 910, y: 1124 }], nails: [[96, 80], [984, 80], [96, 1270], [984, 1270]],
    t: { title: [540, 182, 800, 126], name: [540, 1052, 800, 84], reward: [540, 1204, 800, 46] } },
  landscape: { W: 1920, H: 1080, P: { x: 140, y: 26, w: 1640, h: 1030 }, win: { x: 500, y: 290, w: 920, h: 480 }, margin: 34,
    rules: [{ x1: 240, x2: 1680, y: 224 }], diamonds: [{ x1: 400, x2: 1520, y: 920 }], nails: [[176, 62], [1744, 62], [176, 1020], [1744, 1020]],
    t: { title: [960, 142, 1100, 120], name: [960, 862, 1300, 84], reward: [960, 982, 1300, 52] } },
};
async function build(outDir) {
  const res = {};
  for (const [o, g] of Object.entries(GEOM)) {
    const b = bg(g.W, g.H, g.P);
    let svg = b.s + ornaments(g.P, { margin: g.margin, rules: g.rules, diamonds: g.diamonds });
    for (const [nx, ny] of g.nails) svg += nail(nx, ny, 1);
    svg += '</svg>';
    const bgb = await L.svgToBuf(svg.replace('<svg ', '<svg ').replace(/<\/svg>\s*<\/svg>/, '</svg>'));
    const ovb = await L.svgToBuf(overlay(g.W, g.H, g.win));
    res[o] = [await L.savePng(bgb, `${outDir}/${o}-bg.png`, { colors: 128, dither: .2 }), await L.savePng(ovb, `${outDir}/${o}-overlay.png`, { colors: 64 })];
  }
  return res;
}
module.exports = { build, GEOM };
if (require.main === module) build(L.FR + '/wanted_poster').then(r => console.log(JSON.stringify(r)));
