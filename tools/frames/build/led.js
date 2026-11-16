const L = require('../lib'); const { rng, f1 } = L;

const NEON = ['#ff2d95', '#18e0ff', '#ffd23f', '#8a5cff', '#ff6a2b', '#3dff9a', '#ff3b3b'];

function buildings(r, W, H, o) {
  // o: {baseY, minW,maxW,minH,maxH, fill:[c1,c2], winProb, layer}
  let x = o.x0 ?? -40, out = '', signs = '', glows = '';
  while (x < (o.x1 ?? W + 40)) {
    const bw = r.int(o.minW, o.maxW), bh = r.int(o.minH, o.maxH), y = o.baseY - bh;
    const id = 'b' + Math.floor(r() * 1e9);
    out += `<linearGradient id="${id}" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="${o.fill[0]}"/><stop offset="1" stop-color="${o.fill[1]}"/></linearGradient>`;
    out += `<rect x="${x}" y="${y}" width="${bw}" height="${bh + 5}" fill="url(#${id})"/>`;
    // setback / rooftop details
    if (r() < 0.5) { const sw = bw * r.range(0.4, 0.7), sh = r.range(20, 70); out += `<rect x="${f1(x + (bw - sw) / 2)}" y="${f1(y - sh)}" width="${f1(sw)}" height="${f1(sh + 2)}" fill="${o.fill[0]}"/>`; if (r() < .5) out += `<rect x="${f1(x + bw / 2 - 1.5)}" y="${f1(y - sh - r.range(25, 70))}" width="3" height="80" fill="${o.fill[0]}"/>`; }
    if (o.tank && r() < 0.25) { out += `<rect x="${f1(x + bw * .2)}" y="${f1(y - 34)}" width="34" height="30" fill="#0b0818"/><polygon points="${f1(x + bw * .2 - 3)},${f1(y - 34)} ${f1(x + bw * .2 + 17)},${f1(y - 50)} ${f1(x + bw * .2 + 37)},${f1(y - 34)}" fill="#0b0818"/>`; }
    // windows
    const cw = o.winW, ch = o.winH, gx = o.gapX, gy = o.gapY;
    const cols = Math.floor((bw - 12) / (cw + gx)), rows = Math.floor((bh - 16) / (ch + gy));
    const tint = r.pick(['#ffd98a', '#ffe9b8', '#9fe8ff', '#ffc0e8', '#fff3d0']);
    for (let i = 0; i < cols; i++) for (let j = 0; j < rows; j++) {
      const wx = x + 8 + i * (cw + gx), wy = y + 10 + j * (ch + gy);
      if (r() < o.winProb) out += `<rect x="${f1(wx)}" y="${f1(wy)}" width="${cw}" height="${ch}" fill="${r() < .85 ? tint : r.pick(NEON)}" opacity="${f1(r.range(.45, 1))}"/>`;
      else if (o.dark) out += `<rect x="${f1(wx)}" y="${f1(wy)}" width="${cw}" height="${ch}" fill="#0a0716" opacity=".5"/>`;
    }
    // edge light
    out += `<rect x="${x}" y="${y}" width="2" height="${bh}" fill="#ffffff" opacity="${o.edge || .05}"/>`;
    // neon signs (abstract)
    if (o.signs && bw > 90 && r() < o.signs) {
      const c = r.pick(NEON), kind = r.int(0, 3);
      const sx = x + r.range(8, bw * .4), sy = y + r.range(bh * .15, bh * .7);
      let s = '';
      if (kind === 0) { const w = r.range(40, 70), h = r.range(150, 260); s = `<rect x="${f1(sx)}" y="${f1(sy)}" width="${f1(w)}" height="${f1(h)}" rx="8" fill="none" stroke="${c}" stroke-width="5"/>` + [1, 2, 3, 4, 5].map(k => `<rect x="${f1(sx + 10)}" y="${f1(sy + k * h / 6 - 8)}" width="${f1(w - 20)}" height="12" rx="4" fill="${c}"/>`).join(''); }
      else if (kind === 1) { const w = r.range(120, 220), h = r.range(60, 110); s = `<rect x="${f1(sx)}" y="${f1(sy)}" width="${f1(w)}" height="${f1(h)}" rx="10" fill="${c}" opacity=".18"/><rect x="${f1(sx)}" y="${f1(sy)}" width="${f1(w)}" height="${f1(h)}" rx="10" fill="none" stroke="${c}" stroke-width="5"/>` + `<path d="M${f1(sx + 18)} ${f1(sy + h * .65)} q${f1(w * .12)} -${f1(h * .6)} ${f1(w * .22)} 0 t${f1(w * .22)} 0 t${f1(w * .22)} 0" fill="none" stroke="#fff" stroke-width="5" opacity=".9"/>`; }
      else if (kind === 2) { const rr = r.range(34, 60); s = `<circle cx="${f1(sx + rr)}" cy="${f1(sy + rr)}" r="${f1(rr)}" fill="none" stroke="${c}" stroke-width="6"/><circle cx="${f1(sx + rr)}" cy="${f1(sy + rr)}" r="${f1(rr * .5)}" fill="${c}" opacity=".85"/><rect x="${f1(sx + rr * 2 + 12)}" y="${f1(sy + rr - 6)}" width="${f1(rr * 2.2)}" height="12" rx="6" fill="${c}"/>`; }
      else { const w = r.range(90, 170), h = r.range(70, 130); s = `<polygon points="${f1(sx)},${f1(sy + h)} ${f1(sx + w / 2)},${f1(sy)} ${f1(sx + w)},${f1(sy + h)}" fill="none" stroke="${c}" stroke-width="6" stroke-linejoin="round"/><rect x="${f1(sx + w * .3)}" y="${f1(sy + h * .55)}" width="${f1(w * .4)}" height="10" rx="5" fill="${c}"/>`; }
      signs += s; glows += s;
    }
    x += bw - r.int(0, 8);
  }
  return { out, signs, glows };
}

function scene(W, H, g) {
  // g: panel {x,y,w,h} (outer frame), strip {x,y,w,h}, street y
  const r = rng(W * 7 + H);
  const horizon = g.street; const HB = g.hb || H;
  let svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">
<defs>
<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#03041a"/><stop offset=".35" stop-color="#0f0a34"/><stop offset=".7" stop-color="#32144f"/><stop offset="1" stop-color="#7a2a62"/></linearGradient>
<radialGradient id="hz" cx=".5" cy="1" r=".8"><stop offset="0" stop-color="#ff5fa8" stop-opacity=".5"/><stop offset="1" stop-color="#ff5fa8" stop-opacity="0"/></radialGradient>
<filter id="g6" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="7"/></filter>
<filter id="g18" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="22"/></filter>
<filter id="g2"><feGaussianBlur stdDeviation="1.4"/></filter>
<filter id="noise"><feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2" seed="4"/><feColorMatrix values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 .05 0"/></filter>
<linearGradient id="steel" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#1b1d28"/><stop offset=".5" stop-color="#4a4f63"/><stop offset="1" stop-color="#1b1d28"/></linearGradient>
<linearGradient id="road" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1a1230"/><stop offset="1" stop-color="#07050f"/></linearGradient>
<linearGradient id="strip" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0a0a14"/><stop offset=".5" stop-color="#14142a"/><stop offset="1" stop-color="#05050c"/></linearGradient>
</defs>
<rect width="${W}" height="${H}" fill="url(#sky)"/>
<rect width="${W}" height="${H}" fill="url(#hz)"/>`;
  // stars
  for (let i = 0; i < 60; i++) svg += `<circle cx="${f1(r.range(0, W))}" cy="${f1(r.range(0, H * .25))}" r="${f1(r.range(.6, 1.6))}" fill="#fff" opacity="${f1(r.range(.2, .7))}"/>`;
  // far layer
  const far = buildings(r, W, H, { baseY: horizon, minW: 60, maxW: 150, minH: HB * .34, maxH: HB * .72, fill: ['#1c1442', '#241a52'], winW: 4, winH: 6, gapX: 7, gapY: 9, winProb: .22, edge: .03 });
  svg += `<g filter="url(#g2)" opacity=".9">${far.out}</g>`;
  // haze
  svg += `<rect x="0" y="${horizon * .5}" width="${W}" height="${horizon * .5}" fill="#6b2a74" opacity=".16"/>`;
  // mid layer
  const mid = buildings(r, W, H, { baseY: horizon, minW: 110, maxW: 240, minH: HB * .26, maxH: HB * .62, fill: ['#150f33', '#2a1d55'], winW: 8, winH: 11, gapX: 9, gapY: 12, winProb: .42, signs: .7, tank: true, edge: .07 });
  svg += mid.out;
  // near side towers (left/right edges), taller
  const nl = buildings(r, W, H, { x0: -30, x1: Math.max(200, g.panel.x + 60), baseY: horizon + 20, minW: 150, maxW: 260, minH: HB * .55, maxH: HB * .8, fill: ['#0e0a24', '#1d1442'], winW: 10, winH: 14, gapX: 11, gapY: 14, winProb: .5, signs: .9, edge: .09 });
  const nr = buildings(r, W, H, { x0: g.panel.x + g.panel.w - 60, x1: W + 40, baseY: horizon + 20, minW: 150, maxW: 260, minH: HB * .55, maxH: HB * .8, fill: ['#0e0a24', '#1d1442'], winW: 10, winH: 14, gapX: 11, gapY: 14, winProb: .5, signs: .9, edge: .09 });
  svg += nl.out + nr.out;
  const allSigns = mid.signs + nl.signs + nr.signs;
  svg += `<g filter="url(#g18)" opacity=".8">${allSigns}</g><g filter="url(#g6)" opacity=".9">${allSigns}</g><g>${allSigns}</g>`;

  // billboard halo + backing structure
  const p = g.panel, s = g.strip;
  svg += `<rect x="${p.x - 70}" y="${p.y - 60}" width="${p.w + 140}" height="${p.h + 120}" rx="60" fill="#3fd0ff" opacity=".35" filter="url(#g18)"/>`;
  svg += `<rect x="${p.x - 40}" y="${p.y - 30}" width="${p.w + 80}" height="${p.h + 60}" rx="40" fill="#ff3fa5" opacity=".22" filter="url(#g18)"/>`;
  // steel supports: legs from panel bottom to street, with cross bracing
  const legX = [p.x + p.w * .18, p.x + p.w * .82];
  for (const lx of legX) {
    svg += `<rect x="${f1(lx - 22)}" y="${p.y + p.h - 10}" width="44" height="${horizon - (p.y + p.h) + 30}" fill="url(#steel)"/>`;
    for (let yy = p.y + p.h + 20; yy < horizon; yy += 46) svg += `<path d="M${f1(lx - 22)} ${yy} L${f1(lx + 22)} ${yy + 46}" stroke="#0a0a14" stroke-width="4" opacity=".7"/>`;
  }
  // luminous strip (text band)
  svg += `<rect x="${s.x - 14}" y="${s.y - 14}" width="${s.w + 28}" height="${s.h + 28}" rx="26" fill="#ff3fa5" opacity=".55" filter="url(#g18)"/>`;
  svg += `<rect x="${s.x - 14}" y="${s.y - 14}" width="${s.w + 28}" height="${s.h + 28}" rx="26" fill="#18e0ff" opacity=".35" filter="url(#g6)"/>`;
  svg += `<rect x="${s.x - 6}" y="${s.y - 6}" width="${s.w + 12}" height="${s.h + 12}" rx="20" fill="url(#steel)"/>`;
  svg += `<rect x="${s.x}" y="${s.y}" width="${s.w}" height="${s.h}" rx="16" fill="url(#strip)"/>`;
  // neon double outline inside strip
  svg += `<rect x="${s.x + 10}" y="${s.y + 10}" width="${s.w - 20}" height="${s.h - 20}" rx="10" fill="none" stroke="#18e0ff" stroke-width="4" filter="url(#g6)"/><rect x="${s.x + 10}" y="${s.y + 10}" width="${s.w - 20}" height="${s.h - 20}" rx="10" fill="none" stroke="#9af3ff" stroke-width="2"/>`;
  // dotted LED rows inside strip (subtle)
  for (let i = 0; i < Math.floor(s.w / 18); i++) svg += `<circle cx="${s.x + 24 + i * 18}" cy="${s.y + 24}" r="2.2" fill="#ffd23f" opacity=".5"/><circle cx="${s.x + 24 + i * 18}" cy="${s.y + s.h - 24}" r="2.2" fill="#ff3fa5" opacity=".5"/>`;
  // street
  svg += `<rect x="0" y="${horizon}" width="${W}" height="${H - horizon}" fill="url(#road)"/>`;
  svg += `<rect x="0" y="${horizon}" width="${W}" height="3" fill="#ff7ac0" opacity=".35"/>`;
  // wet reflections
  const cols = [...NEON, '#ffd98a'];
  for (let i = 0; i < 46; i++) { const c = r.pick(cols), x = r.range(0, W); svg += `<rect x="${f1(x)}" y="${horizon + 4}" width="${f1(r.range(6, 26))}" height="${f1(r.range(20, H - horizon - 6))}" fill="${c}" opacity="${f1(r.range(.12, .35))}" filter="url(#g6)"/>`; }
  // light trails and crosswalk
  for (let i = 0; i < 9; i++) { const y = horizon + 14 + i * ((H - horizon - 20) / 9), c = r() < .5 ? '#ff3b3b' : '#fff3d0'; svg += `<rect x="${f1(r.range(0, W * .6))}" y="${f1(y)}" width="${f1(r.range(180, 560))}" height="3" fill="${c}" opacity="${f1(r.range(.18, .5))}" filter="url(#g2)"/>`; }
  // people silhouettes
  for (let i = 0; i < 14; i++) { const x = r.range(0, W), h = r.range(34, 60), y = horizon + r.range(2, 14); svg += `<g fill="#07050f"><ellipse cx="${f1(x)}" cy="${f1(y - h)}" rx="6" ry="7"/><path d="M${f1(x - 9)} ${f1(y - h + 8)} h18 l4 ${f1(h * .55)} h-6 l-2 ${f1(h * .45)} h-4 l-1 -${f1(h * .3)} l-1 ${f1(h * .3)} h-4 l-2 -${f1(h * .45)} h-6z"/></g>`; }
  svg += `<radialGradient id="vg" cx=".5" cy=".5" r=".75"><stop offset=".6" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".55"/></radialGradient><rect width="${W}" height="${H}" fill="url(#vg)"/>`;
  return svg + '</svg>';
}

function overlay(W, H, p, win) {
  // p: outer panel rect. window hole = win
  const R = 26;
  const hole = `M${win.x} ${win.y} h${win.w} v${win.h} h-${win.w} z`;
  const outer = `M${p.x + R} ${p.y} h${p.w - 2 * R} a${R} ${R} 0 0 1 ${R} ${R} v${p.h - 2 * R} a${R} ${R} 0 0 1 -${R} ${R} h-${p.w - 2 * R} a${R} ${R} 0 0 1 -${R} -${R} v-${p.h - 2 * R} a${R} ${R} 0 0 1 ${R} -${R} z`;
  const ring = outer + ' ' + hole;
  const bz = win.x - p.x; // bezel thickness
  let s = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">
<defs>
<mask id="nohole"><rect width="${W}" height="${H}" fill="#fff"/><rect x="${win.x}" y="${win.y}" width="${win.w}" height="${win.h}" fill="#000"/></mask>
<linearGradient id="metal" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#d8dbe6"/><stop offset=".18" stop-color="#7d8296"/><stop offset=".42" stop-color="#2b2e3d"/><stop offset=".6" stop-color="#9097ab"/><stop offset=".8" stop-color="#363949"/><stop offset="1" stop-color="#aab0c4"/></linearGradient>
<linearGradient id="brush" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".25"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".35"/></linearGradient>
<filter id="gb" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="14"/></filter>
<filter id="gs"><feGaussianBlur stdDeviation="3"/></filter>
<filter id="grain" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency=".02 .9" numOctaves="2" seed="9"/><feColorMatrix values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 .22 0"/></filter>
<clipPath id="ringclip"><path d="${ring}" clip-rule="evenodd"/></clipPath>
</defs><g mask="url(#nohole)">`;
  // outer glow & shadow
  s += `<path d="${outer}" fill="#000" opacity=".7" filter="url(#gb)" transform="translate(0,14)"/>`;
  s += `<path d="${outer}" fill="none" stroke="#3fd0ff" stroke-width="10" opacity=".55" filter="url(#gb)"/>`;
  // bezel
  s += `<path d="${ring}" fill-rule="evenodd" fill="url(#metal)"/>`;
  s += `<g clip-path="url(#ringclip)"><rect x="${p.x}" y="${p.y}" width="${p.w}" height="${p.h}" filter="url(#grain)"/><rect x="${p.x}" y="${p.y}" width="${p.w}" height="${p.h}" fill="url(#brush)"/>`;
  // inner bevels: darker gasket ring next to window
  s += `<path d="M${win.x - 12} ${win.y - 12} h${win.w + 24} v${win.h + 24} h-${win.w + 24} z M${win.x} ${win.y} v${win.h} h${win.w} v-${win.h} z" fill="#07080e" fill-rule="evenodd"/>`;
  s += `<path d="M${win.x - 12} ${win.y - 12} h${win.w + 24} v${win.h + 24} h-${win.w + 24} z M${win.x - 10} ${win.y - 10} v${win.h + 20} h${win.w + 20} v-${win.h + 20} z" fill="#fff" fill-rule="evenodd" opacity=".12"/>`;
  s += `</g>`;
  // outer bevel strokes
  s += `<path d="${outer}" fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="2"/>`;
  s += `<rect x="${p.x + 8}" y="${p.y + 8}" width="${p.w - 16}" height="${p.h - 16}" rx="${R - 6}" fill="none" stroke="#000" stroke-opacity=".35" stroke-width="2"/>`;
  s += `<rect x="${p.x + 10}" y="${p.y + 10}" width="${p.w - 20}" height="${p.h - 20}" rx="${R - 8}" fill="none" stroke="#fff" stroke-opacity=".25" stroke-width="1.5"/>`;
  // bolts
  const bolts = [];
  const m = Math.round(bz / 2 + 4);
  const nx = Math.round(p.w / 140), ny = Math.round(p.h / 140);
  for (let i = 0; i <= nx; i++) { const x = p.x + 40 + (p.w - 80) * i / nx; bolts.push([x, p.y + bz / 2 - 2], [x, p.y + p.h - bz / 2 + 2]); }
  for (let j = 1; j < ny; j++) { const y = p.y + 40 + (p.h - 80) * j / ny; bolts.push([p.x + bz / 2 - 2, y], [p.x + p.w - bz / 2 + 2, y]); }
  for (const [x, y] of bolts) s += `<circle cx="${f1(x)}" cy="${f1(y)}" r="6.5" fill="#1a1c28"/><circle cx="${f1(x - .8)}" cy="${f1(y - .8)}" r="5.2" fill="#9aa1b8"/><circle cx="${f1(x - 1.6)}" cy="${f1(y - 1.6)}" r="2.2" fill="#fff" opacity=".8"/><path d="M${f1(x - 3.5)} ${f1(y + 1)} l7 -2" stroke="#2b2e3d" stroke-width="1.5"/>`;
  // top spotlight row (on top edge, above frame)
  const n = Math.floor(p.w / 150);
  for (let i = 0; i < n; i++) {
    const cx = p.x + (i + .5) * p.w / n, cy = p.y - 16;
    if (cy < 12) continue;
    s += `<path d="M${f1(cx - 40)} ${f1(cy + 16)} L${f1(cx + 40)} ${f1(cy + 16)} L${f1(cx + 120)} ${f1(cy + 90)} L${f1(cx - 120)} ${f1(cy + 90)}z" fill="#fff5d6" opacity=".06"/>`;
    s += `<rect x="${f1(cx - 26)}" y="${f1(cy - 10)}" width="52" height="22" rx="6" fill="#171925"/><rect x="${f1(cx - 22)}" y="${f1(cy - 6)}" width="44" height="6" rx="3" fill="#7d8296"/><ellipse cx="${f1(cx)}" cy="${f1(cy + 12)}" rx="20" ry="5" fill="#fff6d8"/><ellipse cx="${f1(cx)}" cy="${f1(cy + 12)}" rx="34" ry="9" fill="#fff6d8" opacity=".5" filter="url(#gs)"/>`;
  }
  // edge reflections/gloss on bezel
  s += `<path d="M${p.x + 6} ${p.y + 6} L${p.x + p.w * .45} ${p.y + 6} L${p.x + p.w * .35} ${p.y + bz - 6} L${p.x + 6} ${p.y + bz - 6}z" fill="#fff" opacity=".12"/>`;
  s += `</g></svg>`;
  return s;
}

const GEOM = {
  portrait: { W: 1080, H: 1350, panel: { x: 70, y: 220, w: 940, h: 850 }, win: { x: 112, y: 262, w: 856, h: 766 }, strip: { x: 120, y: 1112, w: 840, h: 112 }, street: 1262, hb: 1650, text: { x: 540, y: 1168, maxWidth: 780, size: 76 } },
  landscape: { W: 1920, H: 1080, panel: { x: 250, y: 30, w: 1420, h: 810 }, win: { x: 292, y: 72, w: 1336, h: 726 }, strip: { x: 480, y: 872, w: 960, h: 110 }, street: 1004, text: { x: 960, y: 928, maxWidth: 840, size: 76 } },
};
async function build(outDir) {
  const res = {};
  for (const [o, g] of Object.entries(GEOM)) {
    const bg = await L.svgToBuf(scene(g.W, g.H, { panel: g.panel, strip: g.strip, street: g.street, hb: g.hb }));
    const ov = await L.svgToBuf(overlay(g.W, g.H, g.panel, g.win));
    res[o] = [await L.savePng(bg, `${outDir}/${o}-bg.png`, { colors: 200, dither: .6 }), await L.savePng(ov, `${outDir}/${o}-overlay.png`, { colors: 128 })];
  }
  return res;
}
module.exports = { build, GEOM };
if (require.main === module) build(L.FR + '/led_billboard').then(r => console.log(JSON.stringify(r)));
