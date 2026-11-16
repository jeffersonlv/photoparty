const L = require('../lib'); const { rng, f1 } = L; L.registerFonts();

const GEOM = {
  portrait: { W: 1080, H: 1350, B: 30, top: 340, bot: 440, rule: 292,
    bar: { x: 30, y: 934, w: 790, h: 124 }, seal: { x: 902, y: 468, r: 96 }, bc: { x: 806, y: 1166, w: 214, h: 138 },
    t: { masthead: [540, 146, 960, 150], date: [540, 258, 700, 28], h1: [72, 996, 710, 86], h2: [72, 1100, 700, 48] } },
  landscape: { W: 1920, H: 1080, B: 24, top: 320, bot: 380, rule: 268,
    bar: { x: 24, y: 790, w: 1060, h: 116 }, seal: { x: 1730, y: 400, r: 116 }, bc: { x: 1650, y: 872, w: 246, h: 160 },
    t: { masthead: [960, 140, 1500, 176], date: [960, 238, 900, 30], h1: [80, 848, 980, 80], h2: [80, 954, 980, 48] } },
};
function seal(cx, cy, r) {
  let d = ''; const n = 28;
  for (let i = 0; i < n * 2; i++) { const a = i * Math.PI / n, rr = i % 2 ? r * .88 : r; d += (i ? 'L' : 'M') + f1(cx + rr * Math.cos(a)) + ' ' + f1(cy + rr * Math.sin(a)); }
  return `<g transform="rotate(-12 ${cx} ${cy})"><circle cx="${cx + 4}" cy="${cy + 8}" r="${r}" fill="#000" opacity=".45" filter="url(#bl8)"/><path d="${d}z" fill="url(#sealg)"/><path d="${d}z" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="2"/><circle cx="${cx}" cy="${cy}" r="${r * .72}" fill="none" stroke="#fff" stroke-width="3" opacity=".9"/><circle cx="${cx}" cy="${cy}" r="${r * .66}" fill="none" stroke="#ffd0d6" stroke-width="1.5" opacity=".8"/></g>`;
}
function svg(g) {
  const { W, H, B } = g; const r = rng(W);
  const win = { x: B, y: B, w: W - 2 * B, h: H - 2 * B };
  let s = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}"><defs>
<filter id="bl8" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="8"/></filter>
<linearGradient id="topS" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#000" stop-opacity=".6"/><stop offset=".6" stop-color="#000" stop-opacity=".22"/><stop offset="1" stop-color="#000" stop-opacity="0"/></linearGradient>
<linearGradient id="botS" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#000" stop-opacity="0"/><stop offset=".5" stop-color="#000" stop-opacity=".45"/><stop offset="1" stop-color="#000" stop-opacity=".78"/></linearGradient>
<linearGradient id="barg" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#d4162f"/><stop offset="1" stop-color="#e8344a"/></linearGradient>
<radialGradient id="sealg" cx=".35" cy=".3" r=".9"><stop offset="0" stop-color="#ff5a6e"/><stop offset=".6" stop-color="#d4162f"/><stop offset="1" stop-color="#8d0c1e"/></radialGradient>
<linearGradient id="paper" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fbf9f3"/><stop offset="1" stop-color="#e9e5d8"/></linearGradient>
<mask id="nohole"><rect width="${W}" height="${H}" fill="#fff"/><rect x="${win.x}" y="${win.y}" width="${win.w}" height="${win.h}" fill="#000"/></mask>
<mask id="innerm"><rect width="${W}" height="${H}" fill="#fff"/></mask>
</defs>`;
  // scrims (inside window)
  s += `<rect x="${B}" y="${B}" width="${W - 2 * B}" height="${g.top}" fill="url(#topS)"/>`;
  s += `<rect x="${B}" y="${H - B - g.bot}" width="${W - 2 * B}" height="${g.bot}" fill="url(#botS)"/>`;
  // thin white rules around masthead date
  s += `<rect x="${B + 30}" y="${g.rule}" width="${W - 2 * B - 60}" height="2.5" fill="#fff" opacity=".75"/>`;
  // seal
  s += seal(g.seal.x, g.seal.y, g.seal.r);
  // red bar behind headline_1 (shadow + bar + highlight)
  const b = g.bar;
  s += `<rect x="${b.x}" y="${b.y + 6}" width="${b.w}" height="${b.h}" fill="#000" opacity=".4" filter="url(#bl8)"/>`;
  s += `<path d="M${b.x} ${b.y} H${b.x + b.w} l-26 ${b.h / 2} l26 ${b.h / 2} H${b.x}z" fill="url(#barg)"/><rect x="${b.x}" y="${b.y}" width="${b.w - 26}" height="3" fill="#fff" opacity=".35"/>`;
  // barcode box
  const c = g.bc;
  s += `<rect x="${c.x + 3}" y="${c.y + 6}" width="${c.w}" height="${c.h}" rx="6" fill="#000" opacity=".4" filter="url(#bl8)"/><rect x="${c.x}" y="${c.y}" width="${c.w}" height="${c.h}" rx="6" fill="#fff"/>`;
  let x = c.x + 18; const bh = c.h - 52;
  while (x < c.x + c.w - 18) { const w = r.pick([2, 2, 3, 4, 6]); if (x + w > c.x + c.w - 18) break; s += `<rect x="${x}" y="${c.y + 14}" width="${w}" height="${bh}" fill="#111"/>`; x += w + r.pick([2, 3, 4, 5]); }
  s += `<rect x="${B}" y="${B}" width="0" height="0"/>`;
  // cover border (paper) with hole at window
  const ring = `M0 0 H${W} V${H} H0z M${win.x} ${win.y} v${win.h} h${win.w} v-${win.h}z`;
  s += `<path d="${ring}" fill="url(#paper)" fill-rule="evenodd"/>`;
  s += `<path d="M${win.x - 1} ${win.y - 1} h${win.w + 2} v${win.h + 2} h-${win.w + 2} z" fill="none" stroke="#000" stroke-opacity=".25" stroke-width="2"/>`;
  s += `<rect x="1.5" y="1.5" width="${W - 3}" height="${H - 3}" fill="none" stroke="#b9b3a0" stroke-width="3"/>`;
  // spine shading on the left edge
  s += `<rect x="0" y="0" width="10" height="${H}" fill="#000" opacity=".12"/>`;
  return s + '</svg>';
}
async function build(outDir) {
  const res = {};
  for (const [o, g] of Object.entries(GEOM)) {
    const base = await L.svgToBuf(svg(g));
    const png = await L.withCanvas(base, g.W, g.H, async (ctx) => {
      // seal text
      const { x, y, r } = g.seal; ctx.save(); ctx.translate(x, y); ctx.rotate(-12 * Math.PI / 180);
      ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.font = `${r * .46}px Anton`; ctx.letterSpacing = '2px'; ctx.fillText('EDIÇÃO', 0, -r * .2);
      ctx.font = `${r * .4}px Anton`; ctx.fillText('ESPECIAL', 0, r * .26);
      ctx.font = `${r * .2}px Inter`; ctx.fillText('★ ★ ★', 0, r * .58); ctx.restore();
      // barcode digits
      const c = g.bc; ctx.fillStyle = '#111'; ctx.font = `${c.h * .12}px Inter`; ctx.textAlign = 'center'; ctx.letterSpacing = '3px';
      ctx.fillText('7 891234 560017', c.x + c.w / 2, c.y + c.h - 18);
    });
    res[o] = await L.savePng(png, `${outDir}/${o}-overlay.png`, { colors: 128 });
  }
  return res;
}
module.exports = { build, GEOM };
if (require.main === module) build(L.FR + '/magazine_cover').then(r => console.log(JSON.stringify(r)));
