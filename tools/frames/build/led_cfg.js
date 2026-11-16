const { GEOM } = require('./led'); const writeCfg = require('./mkcfg');
const o = {};
for (const [k, g] of Object.entries(GEOM)) o[k] = {
  width: g.W, height: g.H, background: `${k}-bg.png`, window: g.win, overlay: `${k}-overlay.png`,
  texts: [{ id: 'event_name', x: g.text.x, y: g.text.y, maxWidth: g.text.maxWidth, font: 'Anton', size: g.text.size, color: '#FFF1B8', align: 'center', baseline: 'middle', transform: 'uppercase', maxChars: 40, filledBy: 'organizer', label: { pt: 'Nome do evento', en: 'Event name' }, default: { pt: 'NOSSO EVENTO', en: 'OUR EVENT' }, shadow: { color: 'rgba(255,170,60,0.95)', blur: 22, offsetX: 0, offsetY: 0 } }],
  effect: { type: 'led', params: { dotSize: 6, gap: 2, glow: 0.35 } },
};
writeCfg('led_billboard', { pt: 'Painel de LED', en: 'LED Billboard' }, o);
