const sharp = require('sharp');
const fs = require('fs');
const path = require('path');
const { createCanvas, loadImage, GlobalFonts } = require('@napi-rs/canvas');

const FR = path.resolve(__dirname, '../frames');
const FONTS = path.join(FR, '_fonts');
const FONT_FILES = [
  ['Anton', 'anton-400.woff2'], ['Playfair Display', 'playfair-display-400.woff2'],
  ['Rye', 'rye-400.woff2'], ['Great Vibes', 'great-vibes-400.woff2'], ['Inter', 'inter-400.woff2'],
];
function registerFonts() {
  for (const [fam, f] of FONT_FILES) GlobalFonts.registerFromPath(path.join(FONTS, f), fam);
}
function rng(seed) { // mulberry32
  let a = seed >>> 0;
  const r = () => { a |= 0; a = (a + 0x6D2B79F5) | 0; let t = Math.imul(a ^ (a >>> 15), 1 | a); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  r.range = (a, b) => a + (b - a) * r();
  r.int = (a, b) => Math.floor(r.range(a, b + 1));
  r.pick = (arr) => arr[Math.floor(r() * arr.length)];
  return r;
}
async function svgToBuf(svg) { return sharp(Buffer.from(svg)).png().toBuffer(); }
const f1 = (n) => Math.round(n * 10) / 10;

// Salva PNG otimizado (palette com alfa) e retorna tamanho
async function savePng(buf, file, opts = {}) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  let colors = opts.colors || 256, out;
  for (;;) {
    out = await sharp(buf).png({ palette: true, colors, quality: opts.quality || 75, effort: 10, dither: opts.dither ?? 0.7 }).toBuffer();
    if (out.length <= 590 * 1024 || colors <= 32) break;
    colors = Math.floor(colors * 0.8); opts = { ...opts, dither: Math.max(0, (opts.dither ?? .7) - .15) };
  }
  fs.writeFileSync(file, out);
  return { file, bytes: out.length, colors };
}
// canvas helper: desenha buffer png base, permite desenhar texto por cima e devolve png buffer
async function withCanvas(baseBuf, w, h, draw) {
  const cv = createCanvas(w, h); const ctx = cv.getContext('2d');
  if (baseBuf) ctx.drawImage(await loadImage(baseBuf), 0, 0);
  await draw(ctx);
  return cv.toBuffer('image/png');
}
// texto com espacamento de letras (canvas) – ctx.letterSpacing suportado
function spaced(ctx, txt, x, y, ls) { ctx.letterSpacing = ls + 'px'; ctx.fillText(txt, x, y); ctx.letterSpacing = '0px'; }
module.exports = { sharp, fs, path, FR, rng, svgToBuf, savePng, withCanvas, registerFonts, createCanvas, loadImage, GlobalFonts, f1, spaced };
