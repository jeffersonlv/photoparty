import { describe, expect, it } from 'vitest';
import { resolveAssetUrl, validateFrameConfig } from '../../resources/js/guest/frame-config.js';
import { framesForOrientation, normalizeEventConfig } from '../../resources/js/guest/guest-app.js';

// Modulo 8: molduras personalizadas (config gerado pelo servidor, uma ou duas orientacoes).
const customConfig = (orientations) => ({
  version: 1,
  key: '6f1c2a0e-6d7b-4c55-9a3e-2f4b8d1e9c10',
  name: { pt: 'Minha marca', en: 'Minha marca' },
  orientations: Object.fromEntries(orientations.map((o) => [o, o === 'portrait'
    ? { width: 1080, height: 1350, background: null, window: { x: 90, y: 90, w: 900, h: 1010 }, overlay: 'portrait.png', texts: [], effect: null }
    : { width: 1920, height: 1080, background: null, window: { x: 120, y: 90, w: 1680, h: 760 }, overlay: 'landscape.png', texts: [], effect: null }])),
});

describe('molduras personalizadas', () => {
  it('config gerado pelo servidor com uma so orientacao e valido para o motor', () => {
    expect(validateFrameConfig(customConfig(['portrait']), { requireBoth: false }).valid).toBe(true);
    expect(validateFrameConfig(customConfig(['landscape']), { requireBoth: false }).valid).toBe(true);
    expect(validateFrameConfig(customConfig(['portrait', 'landscape'])).valid).toBe(true);
  });

  it('repassa o token do evento do config para o PNG (mesma rota autorizada)', () => {
    const url = '/e/festa/frames/6f1c2a0e-6d7b-4c55-9a3e-2f4b8d1e9c10/config.json?t=abc123';
    expect(resolveAssetUrl(url, 'portrait.png'))
      .toBe('/e/festa/frames/6f1c2a0e-6d7b-4c55-9a3e-2f4b8d1e9c10/portrait.png?t=abc123');
  });

  it('modelos prontos (sem query) continuam com o caminho simples', () => {
    expect(resolveAssetUrl('/frames/floral/config.json', 'portrait-bg.png')).toBe('/frames/floral/portrait-bg.png');
  });

  it('normaliza as orientacoes do payload e ignora valores desconhecidos', () => {
    const c = normalizeEventConfig({
      frames: [
        { key: 'a', configUrl: '/a.json', orientations: ['portrait'] },
        { key: 'b', configUrl: '/b.json', orientations: ['square', 'landscape'] },
        { key: 'c', configUrl: '/c.json', orientations: ['square'] },
        { key: 'd', configUrl: '/d.json' },
      ],
    });
    expect(c.frames.map((f) => f.orientations)).toEqual([
      ['portrait'], ['landscape'], ['portrait', 'landscape'], ['portrait', 'landscape'],
    ]);
  });

  it('moldura de uma orientacao so aparece para fotos daquela orientacao', () => {
    const { frames } = normalizeEventConfig({
      frames: [
        { key: 'tpl', configUrl: '/frames/floral/config.json' },
        { key: 'so-retrato', configUrl: '/x.json', orientations: ['portrait'] },
        { key: 'so-paisagem', configUrl: '/y.json', orientations: ['landscape'] },
      ],
    });
    expect(framesForOrientation(frames, 'portrait').map((f) => f.key)).toEqual(['tpl', 'so-retrato']);
    expect(framesForOrientation(frames, 'landscape').map((f) => f.key)).toEqual(['tpl', 'so-paisagem']);
  });
});
