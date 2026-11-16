const { GEOM } = require('./wanted'); const writeCfg = require('./mkcfg');
const o = {};
const INK = '#3B2412';
for (const [k, g] of Object.entries(GEOM)) {
  const t = g.t, base = { align: 'center', baseline: 'middle', maxChars: 40, shadow: { color: 'rgba(255,240,200,0.35)', blur: 1, offsetX: 1, offsetY: 1 } };
  o[k] = { width: g.W, height: g.H, background: `${k}-bg.png`, window: g.win, overlay: `${k}-overlay.png`,
    texts: [
      { id: 'wanted_title', x: t.title[0], y: t.title[1], maxWidth: t.title[2], font: 'Rye', size: t.title[3], color: INK, ...base, transform: 'uppercase', filledBy: 'organizer', label: { pt: 'Título do cartaz', en: 'Poster title' }, default: { pt: 'PROCURADO', en: 'WANTED' } },
      { id: 'guest_name', x: t.name[0], y: t.name[1], maxWidth: t.name[2], font: 'Rye', size: t.name[3], color: INK, ...base, transform: 'uppercase', filledBy: 'guest', label: { pt: 'Nome do convidado', en: 'Guest name' }, default: { pt: 'SEU NOME', en: 'YOUR NAME' } },
      { id: 'reward', x: t.reward[0], y: t.reward[1], maxWidth: t.reward[2], font: 'Rye', size: t.reward[3], color: '#8A2410', ...base, transform: 'uppercase', filledBy: 'organizer', label: { pt: 'Recompensa', en: 'Reward' }, default: { pt: 'RECOMPENSA: UM BRINDE', en: 'REWARD: ONE TOAST' } },
    ], effect: { type: 'sepia', params: { intensity: 1 } } };
}
writeCfg('wanted_poster', { pt: 'Cartaz de Procurado', en: 'Wanted Poster' }, o);
