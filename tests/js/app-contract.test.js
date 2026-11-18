// Consistencia entre contrato, molduras de teste da demo, traducoes e marcacao Blade.
import { describe, expect, it } from 'vitest';
import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { parsePhpLang } from '../../tools/guest-demo/scripts/lang.mjs';
import { createTranslator } from '../../resources/js/guest/i18n.js';
import { normalizeEventConfig } from '../../resources/js/guest/guest-app.js';
import { validateFrameConfig, ORIENTATIONS } from '../../resources/js/guest/frame-config.js';
import { FILTER_KEYS } from '../../resources/js/guest/effects.js';

const root = resolve(import.meta.dirname, '../..');
const read = (p) => readFileSync(resolve(root, p), 'utf8');

describe('molduras de teste da demo', () => {
  for (const key of ['test_led', 'test_sepia']) {
    it(`${key}: config valido e PNGs existem com o tamanho certo`, () => {
      const cfg = JSON.parse(read(`tools/guest-demo/demo/public/frames/${key}/config.json`));
      expect(validateFrameConfig(cfg)).toEqual({ valid: true, errors: [] });
      for (const [name, o] of Object.entries(cfg.orientations)) {
        for (const file of [o.background, o.overlay]) {
          const path = resolve(root, 'tools/guest-demo/demo/public/frames', key, file);
          expect(existsSync(path), path).toBe(true);
          const buf = readFileSync(path);
          // IHDR do PNG: largura/altura nos bytes 16-23
          expect(buf.readUInt32BE(16)).toBe(ORIENTATIONS[name].width);
          expect(buf.readUInt32BE(20)).toBe(ORIENTATIONS[name].height);
        }
      }
    });
  }
});

describe('traducoes e Blade', () => {
  const pt = parsePhpLang(resolve(root, 'lang/pt_BR/guest.php'));
  const en = parsePhpLang(resolve(root, 'lang/en/guest.php'));

  it('pt_BR e en tem exatamente as mesmas chaves e placeholders', () => {
    expect(Object.keys(en).sort()).toEqual(Object.keys(pt).sort());
    const ph = (s) => (s.match(/:[a-z]+/g) ?? []).sort().join();
    for (const k of Object.keys(pt)) expect(ph(en[k]), k).toBe(ph(pt[k]));
  });

  it('toda chave usada no Blade existe nas traducoes', () => {
    const used = new Set();
    for (const f of ['resources/views/guest/show.blade.php', 'resources/views/guest/partials/app.blade.php']) {
      for (const m of read(f).matchAll(/__\('guest\.([a-z0-9_]+)'\)/g)) used.add(m[1]);
    }
    expect(used.size).toBeGreaterThan(30);
    for (const k of used) expect(pt[k], k).toBeTypeOf('string');
  });

  it('toda chave usada pelo JS existe (t(...), filtros)', () => {
    const src = read('resources/js/guest/guest-app.js');
    const keys = new Set([...src.matchAll(/\bt\('([a-z_]+)'/g)].map((m) => m[1]));
    for (const m of src.matchAll(/'(camera_[a-z]+|file_[a-z_]+|error_[a-z_]+|done_[a-z]+)'/g)) keys.add(m[1]);
    for (const f of FILTER_KEYS) keys.add(`filter_${f}`);
    for (const k of keys) expect(pt[k], k).toBeTypeOf('string');
  });

  it('createTranslator troca placeholders e devolve a chave se faltar', () => {
    const t = createTranslator(pt);
    expect(t('chars_left', { count: 7 })).toBe('7 caracteres restantes');
    expect(t('nao_existe')).toBe('nao_existe');
  });

  it('nenhum recurso externo na marcacao nem no CSS', () => {
    for (const f of ['resources/views/guest/show.blade.php', 'resources/views/guest/partials/app.blade.php', 'resources/css/guest.css']) {
      expect(read(f), f).not.toMatch(/https?:\/\/(?!www\.w3\.org)/);
    }
  });
});

describe('normalizeEventConfig', () => {
  it('filters: usa os ativos do evento (chaves canonicas) e cai em todos sem a lista', () => {
    expect(normalizeEventConfig({ filters: ['none', 'bw', 'vintage'] }).filters).toEqual(['none', 'bw', 'vintage']);
    expect(normalizeEventConfig({ filters: ['sepia'] }).filters).toEqual(['none', 'vintage']);
    expect(normalizeEventConfig({}).filters).toEqual(['none', 'bw', 'vintage', 'vivid', 'cool', 'warm']);
  });

  it('aplica padroes e descarta molduras invalidas', () => {
    const c = normalizeEventConfig({
      name: 'Festa', moderation: 1, frames: [{ key: 'a', name: 'A', configUrl: '/a.json' }, { key: 'b' }, null],
    });
    expect(c.moderation).toBe(true);
    expect(c.language).toBe('pt_BR');
    expect(c.askGuestName).toBe(false);
    expect(c.frames).toEqual([{ key: 'a', name: 'A', configUrl: '/a.json', orientations: ['portrait', 'landscape'], texts: {} }]);
    expect(normalizeEventConfig(undefined).frames).toEqual([]);
  });
});

describe('compatibilidade com CSP (@alpinejs/csp)', () => {
  const html = read('resources/views/guest/partials/app.blade.php');
  const exprs = [...html.matchAll(/\sx-(?:on|bind|show|text|model|if|for|data)[a-z:.-]*="([^"]*)"/g)].map((m) => m[1]);

  it('expressoes Alpine sao so identificadores, membros e chamadas simples', () => {
    expect(exprs.length).toBeGreaterThan(40);
    for (const e of exprs) expect(e, e).not.toMatch(/===|!==|\?|&&|\|\||=>|;|[{}]|\s\+\s|(^|\()!|\s=\s/);
  });

  it('show.blade.php nao tem script inline executavel nem handlers inline', () => {
    const show = read('resources/views/guest/show.blade.php');
    expect(show).not.toMatch(/<script(?![^>]*type="application\/json")[^>]*>/);
    expect(show).toMatch(/id="pp-event"/);
    expect(show).toMatch(/JSON_HEX_TAG/);
    expect(html).not.toMatch(/\son[a-z]+=/);
  });
});
