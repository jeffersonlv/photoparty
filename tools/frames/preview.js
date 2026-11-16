// Renderiza prévias seguindo a ordem do contrato e valida configs
const L = require('./lib'); L.registerFonts();
const { createCanvas, loadImage } = L; const fs = L.fs, path = L.path;
const KEYS = ['led_billboard', 'magazine_cover', 'wanted_poster', 'floral'];
const SIZES = { portrait: [1080, 1350], landscape: [1920, 1080] };
const MIN_TRANSPARENT = { led_billboard: .995, wanted_poster: .995, magazine_cover: .2, floral: .9 };
const FONTS = ['Anton', 'Playfair Display', 'Rye', 'Great Vibes', 'Inter'];

async function validate(key) {
  const errs = [];
  const dir = path.join(L.FR, key);
  let cfg; try { cfg = JSON.parse(fs.readFileSync(path.join(dir, 'config.json'), 'utf8')); } catch (e) { return [`${key}: config.json inválido: ${e.message}`]; }
  const E = (m) => errs.push(`${key}: ${m}`);
  if (cfg.version !== 1) E('version != 1');
  if (cfg.key !== key) E('key difere da pasta');
  if (!cfg.name?.pt || !cfg.name?.en) E('name pt/en');
  const ids = {};
  for (const [o, [W, H]] of Object.entries(SIZES)) {
    const c = cfg.orientations?.[o]; if (!c) { E(`orientação ${o} ausente`); continue; }
    if (c.width !== W || c.height !== H) E(`${o}: tamanho ${c.width}x${c.height}`);
    const w = c.window; if (!w || ![w.x, w.y, w.w, w.h].every(Number.isInteger) || w.w <= 0 || w.h <= 0 || w.x < 0 || w.y < 0 || w.x + w.w > W || w.y + w.h > H) E(`${o}: janela fora da imagem`);
    for (const k of ['background', 'overlay']) {
      if (c[k] === undefined) E(`${o}: ${k} ausente`);
      else if (c[k] !== null) {
        const f = path.join(dir, c[k]);
        if (!fs.existsSync(f)) { E(`${o}: ${k} não existe (${c[k]})`); continue; }
        const st = fs.statSync(f).size; if (st > 600 * 1024) E(`${o}: ${k} ${Math.round(st / 1024)}KB > 600KB`);
        const img = await loadImage(f); if (img.width !== W || img.height !== H) E(`${o}: ${k} dimensão ${img.width}x${img.height}`);
        if (k === 'overlay' && w) {
          const cv = createCanvas(W, H), cx = cv.getContext('2d'); cx.drawImage(img, 0, 0);
          const d = cx.getImageData(w.x, w.y, w.w, w.h).data; let t = 0; for (let i = 3; i < d.length; i += 4) if (d[i] <= 10) t++;
          const frac = t / (d.length / 4); if (frac < MIN_TRANSPARENT[key]) E(`${o}: overlay só ${(frac * 100).toFixed(1)}% transparente na janela (mín ${MIN_TRANSPARENT[key] * 100}%)`);
          c._tr = frac;
        }
      }
    }
    if (!Array.isArray(c.texts)) E(`${o}: texts`);
    for (const t of c.texts || []) {
      ids[t.id] = (ids[t.id] || 0) + 1;
      for (const k of ['id', 'x', 'y', 'maxWidth', 'font', 'size', 'color', 'align', 'baseline', 'filledBy', 'label', 'default']) if (t[k] === undefined) E(`${o}/${t.id}: falta ${k}`);
      if (!FONTS.includes(t.font)) E(`${o}/${t.id}: fonte ${t.font}`);
      if (!['left', 'center', 'right'].includes(t.align)) E(`${o}/${t.id}: align`);
      if (!['top', 'middle', 'alphabetic', 'bottom'].includes(t.baseline)) E(`${o}/${t.id}: baseline`);
      if (!['organizer', 'guest'].includes(t.filledBy)) E(`${o}/${t.id}: filledBy`);
      if (t.transform !== null && t.transform !== 'uppercase') E(`${o}/${t.id}: transform`);
      if (t.filledBy === 'guest' && !(t.maxChars <= 40)) E(`${o}/${t.id}: guest exige maxChars<=40`);
      for (const k of ['label', 'default']) if (!t[k]?.pt || !t[k]?.en) E(`${o}/${t.id}: ${k} pt/en`);
      if (t.x < 0 || t.x > W || t.y < 0 || t.y > H || t.maxWidth > W) E(`${o}/${t.id}: posição fora`);
    }
    if (c.effect !== null && !['led', 'sepia'].includes(c.effect?.type)) E(`${o}: effect.type`);
  }
  const lp = Object.keys(ids); for (const id of lp) if (ids[id] !== 2) E(`texto ${id} não está nas duas orientações`);
  return { errs, cfg };
}

function testPhoto(w, h) {
  const cv = createCanvas(w, h), x = cv.getContext('2d');
  let g = x.createLinearGradient(0, 0, w, h); g.addColorStop(0, '#f6a35c'); g.addColorStop(.5, '#d9658b'); g.addColorStop(1, '#3a5fb8'); x.fillStyle = g; x.fillRect(0, 0, w, h);
  x.fillStyle = 'rgba(255,255,255,.25)'; x.beginPath(); x.arc(w * .75, h * .25, h * .18, 0, 7); x.fill();
  x.fillStyle = '#2b2140'; x.beginPath(); x.moveTo(0, h); x.lineTo(w * .3, h * .55); x.lineTo(w * .55, h); x.fill();
  // "pessoas": 2 silhuetas com cabeça
  for (const [cx, s] of [[.4, 1], [.6, .9]]) { x.fillStyle = '#f0c8a8'; x.beginPath(); x.arc(w * cx, h * .38, h * .1 * s, 0, 7); x.fill(); x.fillStyle = '#1d1d2e'; x.beginPath(); x.ellipse(w * cx, h * .85, h * .2 * s, h * .33 * s, 0, Math.PI, 0); x.fill(); }
  x.fillStyle = '#ffe14d'; x.fillRect(w * .08, h * .1, w * .12, h * .06);
  return cv;
}
function fit(ctx, t, txt) {
  ctx.font = `${t.size}px "${t.font}"`; const m = ctx.measureText(txt).width; let size = t.size;
  if (m > t.maxWidth) size = t.size * t.maxWidth / m; return size;
}
async function render(key, cfg, o, lang = 'pt') {
  const c = cfg.orientations[o], dir = path.join(L.FR, key);
  const cv = createCanvas(c.width, c.height), ctx = cv.getContext('2d');
  ctx.fillStyle = '#888'; ctx.fillRect(0, 0, c.width, c.height);
  if (c.background) ctx.drawImage(await loadImage(path.join(dir, c.background)), 0, 0);
  const w = c.window; ctx.save(); ctx.beginPath(); ctx.rect(w.x, w.y, w.w, w.h); ctx.clip();
  const ph = testPhoto(w.w, w.h); ctx.drawImage(ph, w.x, w.y);
  if (c.effect?.type === 'led') {
    const { dotSize: ds = 6, gap = 2, glow = .35 } = c.effect.params || {}; const st = ds + gap;
    const tmp = createCanvas(w.w, w.h), tx = tmp.getContext('2d'); tx.drawImage(ph, 0, 0);
    const src = tx.getImageData(0, 0, w.w, w.h).data;
    ctx.fillStyle = '#05050a'; ctx.fillRect(w.x, w.y, w.w, w.h);
    for (let y = 0; y < w.h; y += st) for (let x = 0; x < w.w; x += st) {
      const i = ((y + (ds >> 1)) * w.w + x + (ds >> 1)) * 4; if (src[i + 3] === undefined) continue;
      ctx.fillStyle = `rgb(${Math.min(255, src[i] * 1.15)},${Math.min(255, src[i + 1] * 1.15)},${Math.min(255, src[i + 2] * 1.15)})`;
      ctx.beginPath(); ctx.arc(w.x + x + ds / 2, w.y + y + ds / 2, ds / 2, 0, 7); ctx.fill();
    }
    ctx.globalCompositeOperation = 'screen'; ctx.globalAlpha = glow; ctx.filter = 'blur(8px)'; ctx.drawImage(cv, 0, 0); ctx.filter = 'none'; ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
  } else if (c.effect?.type === 'sepia') {
    const id = ctx.getImageData(w.x, w.y, w.w, w.h), d = id.data;
    for (let i = 0; i < d.length; i += 4) { const r = d[i], g = d[i + 1], b = d[i + 2]; d[i] = Math.min(255, r * .393 + g * .769 + b * .189); d[i + 1] = Math.min(255, r * .349 + g * .686 + b * .168); d[i + 2] = Math.min(255, r * .272 + g * .534 + b * .131); }
    ctx.putImageData(id, w.x, w.y);
  }
  ctx.restore();
  if (c.overlay) ctx.drawImage(await loadImage(path.join(dir, c.overlay)), 0, 0);
  const boxes = [];
  for (const t of c.texts) {
    let txt = t.default[lang]; if (t.transform === 'uppercase') txt = txt.toUpperCase();
    const size = fit(ctx, t, txt); ctx.font = `${size}px "${t.font}"`; ctx.fillStyle = t.color; ctx.textAlign = t.align; ctx.textBaseline = t.baseline;
    if (t.shadow) { ctx.shadowColor = t.shadow.color; ctx.shadowBlur = t.shadow.blur; ctx.shadowOffsetX = t.shadow.offsetX; ctx.shadowOffsetY = t.shadow.offsetY; }
    ctx.fillText(txt, t.x, t.y); ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0;
    if (process.env.BOXES) { const m = ctx.measureText(txt); ctx.strokeStyle = 'rgba(0,255,0,.8)'; ctx.strokeRect(t.align === 'center' ? t.x - m.width / 2 : t.x, t.y - m.actualBoundingBoxAscent, m.width, m.actualBoundingBoxAscent + m.actualBoundingBoxDescent); }
  }
  return cv;
}
(async () => {
  let bad = 0; const only = process.argv[2];
  for (const key of KEYS) {
    if (only && only !== key) continue;
    if (!fs.existsSync(path.join(L.FR, key, 'config.json'))) { console.log('skip', key); continue; }
    const r = await validate(key);
    if (Array.isArray(r)) { console.log(r.join('\n')); bad++; continue; }
    if (r.errs.length) { bad += r.errs.length; console.log(r.errs.join('\n')); } else console.log(`OK ${key}`);
    for (const o of Object.keys(SIZES)) {
      const cv = await render(key, r.cfg, o);
      fs.writeFileSync(path.join(L.FR, '_previews', `${key}-${o}.jpg`), cv.toBuffer('image/jpeg', 88));
    }
  }
  process.exit(bad ? 1 : 0);
})();
