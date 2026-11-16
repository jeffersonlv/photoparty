const { GEOM } = require('./floral'); const writeCfg = require('./mkcfg');
const o = {};
for (const [k, g] of Object.entries(GEOM)) {
  const t = g.t;
  o[k] = { width: g.W, height: g.H, background: `${k}-bg.png`, window: g.win, overlay: `${k}-overlay.png`,
    texts: [
      { id: 'event_name', x: t.name[0], y: t.name[1], maxWidth: t.name[2], font: 'Great Vibes', size: t.name[3], color: '#8A4B5A', align: 'center', baseline: 'middle', transform: null, maxChars: 40, filledBy: 'organizer', label: { pt: 'Nome do evento', en: 'Event name' }, default: { pt: 'Ana & Pedro', en: 'Ana & Pedro' } },
      { id: 'event_date', x: t.date[0], y: t.date[1], maxWidth: t.date[2], font: 'Playfair Display', size: t.date[3], color: '#6B5A4E', align: 'center', baseline: 'middle', transform: 'uppercase', maxChars: 40, filledBy: 'organizer', label: { pt: 'Data do evento', en: 'Event date' }, default: { pt: '12 DE DEZEMBRO DE 2026', en: 'DECEMBER 12, 2026' } },
    ], effect: null };
}
writeCfg('floral', { pt: 'Floral', en: 'Floral' }, o);
