// Gera as molduras de teste da demo (config.json + PNGs) com @napi-rs/canvas.
// Uso: npm run demo:frames  -> demo/public/frames/<key>/
import { createCanvas } from '@napi-rs/canvas';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const OUT = resolve(import.meta.dirname, '../demo/public/frames');

const SIZES = { portrait: [1080, 1350], landscape: [1920, 1080] };
const WINDOWS = {
  portrait: { x: 90, y: 170, w: 900, h: 700 },
  landscape: { x: 60, y: 60, w: 1180, h: 960 },
};
// painel de texto: onde ficam os textos de cada orientacao
const TEXT_SLOTS = {
  portrait: { eventY: 1010, guestY: 1150, cx: 540, maxWidth: 900, eventSize: 96, guestSize: 64 },
  landscape: { eventY: 430, guestY: 640, cx: 1580, maxWidth: 560, eventSize: 84, guestSize: 56 },
};

function paintBackground(w, h, tint) {
  const c = createCanvas(w, h);
  const ctx = c.getContext('2d');
  const g = ctx.createLinearGradient(0, 0, 0, h);
  g.addColorStop(0, tint[0]);
  g.addColorStop(1, tint[1]);
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, w, h);
  // grade discreta so para dar textura
  ctx.strokeStyle = 'rgba(255,255,255,0.05)';
  ctx.lineWidth = 2;
  for (let x = 0; x < w; x += 60) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, h); ctx.stroke(); }
  for (let y = 0; y < h; y += 60) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke(); }
  return c;
}

function paintOverlay(w, h, win, accent) {
  const c = createCanvas(w, h);
  const ctx = c.getContext('2d');
  // moldura grossa ao redor da janela; a janela fica 100% transparente
  const b = 18;
  ctx.fillStyle = accent;
  ctx.fillRect(win.x - b, win.y - b, win.w + 2 * b, win.h + 2 * b);
  ctx.clearRect(win.x, win.y, win.w, win.h);
  // cantos decorativos
  ctx.fillStyle = '#ffffff';
  for (const [x, y] of [[win.x - b, win.y - b], [win.x + win.w + b - 36, win.y - b], [win.x - b, win.y + win.h + b - 36], [win.x + win.w + b - 36, win.y + win.h + b - 36]]) {
    ctx.fillRect(x, y, 36, 36);
  }
  return c;
}

function textDefs(o, withEventName) {
  const s = TEXT_SLOTS[o];
  const shadow = { color: 'rgba(0,0,0,0.6)', blur: 12, offsetX: 0, offsetY: 0 };
  const texts = [];
  if (withEventName) {
    texts.push({
      id: 'event_name', x: s.cx, y: s.eventY, maxWidth: s.maxWidth, font: 'Inter', size: s.eventSize,
      color: '#FFFFFF', align: 'center', baseline: 'middle', transform: 'uppercase', maxChars: 40,
      filledBy: 'organizer', label: { pt: 'Nome do evento', en: 'Event name' },
      default: { pt: 'FESTA DEMO', en: 'DEMO PARTY' }, shadow,
    });
  }
  texts.push({
    id: 'guest_name', x: s.cx, y: s.guestY, maxWidth: s.maxWidth, font: 'Inter', size: s.guestSize,
    color: '#FFD166', align: 'center', baseline: 'middle', transform: 'uppercase', maxChars: 24,
    filledBy: 'guest', label: { pt: 'Seu nome', en: 'Your name' },
    default: { pt: 'SEU NOME', en: 'YOUR NAME' }, shadow,
  });
  return texts;
}

function makeFrame({ key, name, effect, tint, accent }) {
  const dir = resolve(OUT, key);
  mkdirSync(dir, { recursive: true });
  const orientations = {};
  for (const o of Object.keys(SIZES)) {
    const [w, h] = SIZES[o];
    writeFileSync(resolve(dir, `${o}-bg.png`), paintBackground(w, h, tint).toBuffer('image/png'));
    writeFileSync(resolve(dir, `${o}-overlay.png`), paintOverlay(w, h, WINDOWS[o], accent).toBuffer('image/png'));
    orientations[o] = {
      width: w, height: h, background: `${o}-bg.png`, window: WINDOWS[o], overlay: `${o}-overlay.png`,
      texts: textDefs(o, key === 'test_led'), effect,
    };
  }
  const config = { version: 1, key, name, orientations };
  writeFileSync(resolve(dir, 'config.json'), `${JSON.stringify(config, null, 2)}\n`);
}

makeFrame({
  key: 'test_led',
  name: { pt: 'Painel de LED (teste)', en: 'LED Panel (test)' },
  effect: { type: 'led', params: { dotSize: 6, gap: 2, glow: 0.35 } },
  tint: ['#0b1030', '#1b0f3a'],
  accent: '#2b2f55',
});
makeFrame({
  key: 'test_sepia',
  name: { pt: 'Cartaz sépia (teste)', en: 'Sepia poster (test)' },
  effect: { type: 'sepia' },
  tint: ['#6b4a2b', '#3b2614'],
  accent: '#8a5a2b',
});
