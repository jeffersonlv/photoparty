import { describe, expect, it } from 'vitest';
import {
  applyTransform, guestTextFields, isSafeRelativePath, limitChars, pickLang, resolveAssetUrl, resolveText, validateFrameConfig,
} from '../../resources/js/guest/frame-config.js';

// exemplo do contrato (frame-config.md), com as duas orientacoes
const orient = (w, h) => ({
  width: w,
  height: h,
  background: 'bg.png',
  window: { x: 120, y: 260, w: 840, h: 620 },
  overlay: 'overlay.png',
  texts: [{
    id: 'event_name', x: 540, y: 1180, maxWidth: 900, font: 'Anton', size: 72, color: '#FFFFFF', align: 'center',
    baseline: 'middle', transform: 'uppercase', maxChars: 40, filledBy: 'organizer',
    label: { pt: 'Nome do evento', en: 'Event name' }, default: { pt: 'NOSSO EVENTO', en: 'OUR EVENT' },
    shadow: { color: 'rgba(0,0,0,0.6)', blur: 12, offsetX: 0, offsetY: 0 },
  }],
  effect: { type: 'led', params: { dotSize: 6, gap: 2, glow: 0.35 } },
});
const valid = () => ({
  version: 1, key: 'led_billboard', name: { pt: 'Painel de LED', en: 'LED Billboard' },
  orientations: { portrait: orient(1080, 1350), landscape: { ...orient(1920, 1080), window: { x: 100, y: 100, w: 1000, h: 800 } } },
});

describe('validateFrameConfig', () => {
  it('aceita o exemplo do contrato', () => {
    expect(validateFrameConfig(valid())).toEqual({ valid: true, errors: [] });
  });

  it('exige as duas orientacoes por padrao, mas aceita uma com requireBoth=false (moldura custom)', () => {
    const c = valid();
    delete c.orientations.landscape;
    expect(validateFrameConfig(c).valid).toBe(false);
    expect(validateFrameConfig(c, { requireBoth: false }).valid).toBe(true);
  });

  it('rejeita tamanho fora do contrato', () => {
    const c = valid();
    c.orientations.portrait.width = 1000;
    expect(validateFrameConfig(c).errors.join()).toMatch(/1080x1350/);
  });

  it('aceita background/overlay nulos, effect nulo e transform nulo', () => {
    const c = valid();
    Object.assign(c.orientations.portrait, { background: null, overlay: null, effect: null });
    c.orientations.portrait.texts[0].transform = null;
    expect(validateFrameConfig(c).valid).toBe(true);
  });

  it('rejeita janela fora do quadro ou vazia', () => {
    const c = valid();
    c.orientations.portrait.window = { x: 900, y: 0, w: 400, h: 100 };
    expect(validateFrameConfig(c).valid).toBe(false);
    c.orientations.portrait.window = { x: 0, y: 0, w: 0, h: 10 };
    expect(validateFrameConfig(c).valid).toBe(false);
  });

  it('rejeita efeito desconhecido', () => {
    const c = valid();
    c.orientations.portrait.effect = { type: 'blur' };
    expect(validateFrameConfig(c).valid).toBe(false);
  });

  it('valida campos de texto: fonte, align, baseline, filledBy e maxChars de convidado', () => {
    const bad = (patch) => {
      const c = valid();
      Object.assign(c.orientations.portrait.texts[0], patch);
      return validateFrameConfig(c);
    };
    expect(bad({ font: 'Comic Sans' }).valid).toBe(false);
    expect(bad({ align: 'justify' }).valid).toBe(false);
    expect(bad({ baseline: 'hanging' }).valid).toBe(false);
    expect(bad({ transform: 'lowercase' }).valid).toBe(false);
    expect(bad({ filledBy: 'admin' }).valid).toBe(false);
    expect(bad({ filledBy: 'guest', maxChars: 41 }).valid).toBe(false);
    expect(bad({ filledBy: 'guest', maxChars: 40 }).valid).toBe(true);
    expect(bad({ maxWidth: 0 }).valid).toBe(false);
  });

  it('rejeita id de texto duplicado, versao errada e lixo', () => {
    const c = valid();
    c.orientations.portrait.texts.push({ ...c.orientations.portrait.texts[0] });
    expect(validateFrameConfig(c).errors.join()).toMatch(/duplicado/);
    expect(validateFrameConfig({ ...valid(), version: 2 }).valid).toBe(false);
    expect(validateFrameConfig(null).valid).toBe(false);
    expect(validateFrameConfig([]).valid).toBe(false);
  });
});

describe('isSafeRelativePath', () => {
  it('so aceita PNG relativo dentro da pasta (sem requisicao externa)', () => {
    expect(isSafeRelativePath('portrait-bg.png')).toBe(true);
    expect(isSafeRelativePath('img/a.png')).toBe(true);
    for (const p of ['http://x.com/a.png', '//x.com/a.png', '/etc/a.png', '../a.png', 'a/../b.png', 'data:image/png;base64,AA', 'a.jpg', '', 'a\\b.png']) {
      expect(isSafeRelativePath(p), p).toBe(false);
    }
  });
});

describe('textos', () => {
  it('pickLang escolhe idioma, cai para pt e depois para o primeiro', () => {
    expect(pickLang({ pt: 'Ola', en: 'Hi' }, 'en')).toBe('Hi');
    expect(pickLang({ pt: 'Ola', en: 'Hi' }, 'pt_BR')).toBe('Ola');
    expect(pickLang({ pt: 'Ola', en: 'Hi' }, 'fr')).toBe('Ola');
    expect(pickLang({ en: 'Hi' }, 'pt')).toBe('Hi');
    expect(pickLang(undefined, 'pt')).toBe('');
  });

  it('limitChars corta por pontos de codigo e limpa controles', () => {
    expect(limitChars('  ab\ncd  ', 10)).toBe('ab cd');
    expect(limitChars('abcdef', 3)).toBe('abc');
    expect(limitChars('😀😀😀', 2)).toBe('😀😀');
    expect(limitChars(undefined, 5)).toBe('');
  });

  it('applyTransform e resolveText (valor > padrao do idioma, maxChars, uppercase)', () => {
    expect(applyTransform('ola', 'uppercase')).toBe('OLA');
    expect(applyTransform('ola', null)).toBe('ola');
    const def = { id: 'n', maxChars: 5, transform: 'uppercase', default: { pt: 'padrao', en: 'default' } };
    expect(resolveText(def, { n: 'abcdefgh' }, 'pt')).toBe('ABCDE');
    expect(resolveText(def, {}, 'en')).toBe('DEFAU');
    expect(resolveText(def, { n: '   ' }, 'pt')).toBe('PADRA');
  });

  it('guestTextFields lista so os de convidado com maxChars <= 40', () => {
    const cfg = valid().orientations.portrait;
    cfg.texts.push({ ...cfg.texts[0], id: 'guest_name', filledBy: 'guest', maxChars: 24, label: { pt: 'Seu nome' } });
    const f = guestTextFields(cfg, 'pt');
    expect(f).toEqual([{ id: 'guest_name', label: 'Seu nome', maxChars: 24, placeholder: 'NOSSO EVENTO' }]);
  });

  it('resolveAssetUrl junta a pasta do config com o caminho relativo', () => {
    expect(resolveAssetUrl('/frames/led/config.json', 'portrait-bg.png')).toBe('/frames/led/portrait-bg.png');
    expect(resolveAssetUrl('/frames/led/config.json?v=3', 'a.png')).toBe('/frames/led/a.png');
  });
});
