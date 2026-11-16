const { GEOM } = require('./magazine'); const writeCfg = require('./mkcfg');
const o = {};
const sh = (b) => ({ color: 'rgba(0,0,0,0.55)', blur: b, offsetX: 0, offsetY: 2 });
for (const [k, g] of Object.entries(GEOM)) {
  const t = g.t, B = g.B;
  const T = (id, a, font, color, align, transform, label, def, shadow) => ({ id, x: a[0], y: a[1], maxWidth: a[2], font, size: a[3], color, align, baseline: 'middle', transform, maxChars: 40, filledBy: 'organizer', label, default: def, shadow });
  o[k] = { width: g.W, height: g.H, background: null, window: { x: B, y: B, w: g.W - 2 * B, h: g.H - 2 * B }, overlay: `${k}-overlay.png`,
    texts: [
      T('magazine_title', t.masthead, 'Playfair Display', '#FFFFFF', 'center', 'uppercase', { pt: 'Nome da revista', en: 'Magazine name' }, { pt: 'PHOTO PARTY', en: 'PHOTO PARTY' }, sh(14)),
      T('issue_date', t.date, 'Inter', '#FFFFFF', 'center', 'uppercase', { pt: 'Data da edição', en: 'Issue date' }, { pt: 'NOVEMBRO 2026', en: 'NOVEMBER 2026' }, sh(6)),
      T('headline_1', t.h1, 'Anton', '#FFFFFF', 'left', 'uppercase', { pt: 'Manchete 1', en: 'Headline 1' }, { pt: 'A NOITE DO ANO', en: 'THE PARTY OF THE YEAR' }, { color: 'rgba(0,0,0,0.35)', blur: 4, offsetX: 0, offsetY: 2 }),
      T('headline_2', t.h2, 'Playfair Display', '#FFFFFF', 'left', null, { pt: 'Manchete 2', en: 'Headline 2' }, { pt: 'Os melhores momentos da festa', en: 'The best moments of the night' }, sh(10)),
    ], effect: null };
}
writeCfg('magazine_cover', { pt: 'Capa de Revista', en: 'Magazine Cover' }, o);
