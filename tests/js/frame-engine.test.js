import { describe, expect, it } from 'vitest';
import {
  baseCoverScale, chooseOrientation, clampView, clampZoom, coverRect, fitFontSize, fitWithin, MAX_ZOOM, plainOrientation, zoomAround,
} from '../../resources/js/guest/frame-engine.js';

const g = { imgW: 4000, imgH: 3000, winW: 840, winH: 620 };

describe('chooseOrientation', () => {
  it('foto em pe -> retrato; deitada -> paisagem', () => {
    expect(chooseOrientation(3000, 4000)).toBe('portrait');
    expect(chooseOrientation(4000, 3000)).toBe('landscape');
    expect(chooseOrientation(1920, 1080)).toBe('landscape');
    expect(chooseOrientation(1080, 1350)).toBe('portrait');
  });
  it('quadrada vai para retrato e entrada invalida e segura', () => {
    expect(chooseOrientation(1000, 1000)).toBe('portrait');
    expect(chooseOrientation(0, 0)).toBe('portrait');
  });
});

describe('fitWithin', () => {
  it('reduz mantendo proporcao e nunca amplia', () => {
    expect(fitWithin(4000, 3000, 2000)).toEqual({ width: 2000, height: 1500 });
    expect(fitWithin(3000, 4000, 2000)).toEqual({ width: 1500, height: 2000 });
    expect(fitWithin(800, 600, 2000)).toEqual({ width: 800, height: 600 });
  });
});

describe('cover / pan / zoom', () => {
  it('escala cover usa o maior fator', () => {
    expect(baseCoverScale(4000, 3000, 840, 620)).toBeCloseTo(Math.max(0.21, 0.2067), 4);
  });

  it('sem zoom a foto cobre a janela e fica centralizada', () => {
    const r = coverRect(g, { zoom: 1, panX: 0, panY: 0 });
    expect(r.w).toBeGreaterThanOrEqual(g.winW - 1e-9);
    expect(r.h).toBeGreaterThanOrEqual(g.winH - 1e-9);
    expect(r.x).toBeCloseTo((g.winW - r.w) / 2, 6);
  });

  it('pan e limitado: a foto sempre cobre a janela', () => {
    for (const zoom of [1, 1.7, 3]) {
      for (const pan of [-5000, 0, 5000]) {
        const r = coverRect(g, { zoom, panX: pan, panY: pan });
        expect(r.x).toBeLessThanOrEqual(1e-9);
        expect(r.y).toBeLessThanOrEqual(1e-9);
        expect(r.x + r.w).toBeGreaterThanOrEqual(g.winW - 1e-9);
        expect(r.y + r.h).toBeGreaterThanOrEqual(g.winH - 1e-9);
      }
    }
  });

  it('zoom e limitado entre 1 e MAX_ZOOM e ignora NaN', () => {
    expect(clampZoom(0.2)).toBe(1);
    expect(clampZoom(99)).toBe(MAX_ZOOM);
    expect(clampZoom(Number.NaN)).toBe(1);
  });

  it('com zoom 1 em foto quadrada na janela quadrada, pan some', () => {
    const v = clampView({ zoom: 1, panX: 50, panY: 50 }, { imgW: 100, imgH: 100, winW: 50, winH: 50 });
    expect(v).toEqual({ zoom: 1, panX: 0, panY: 0 });
  });

  it('zoomAround mantem fixo o ponto sob o foco', () => {
    const geo = { imgW: 2000, imgH: 2000, winW: 500, winH: 500 };
    const start = { zoom: 2, panX: 40, panY: -30 };
    const focal = { x: 100, y: 60 };
    // ponto da foto (em px da foto) sob o foco antes
    const before = coverRect(geo, start);
    const photoPt = { x: (focal.x + geo.winW / 2 - before.x) / before.scale, y: (focal.y + geo.winH / 2 - before.y) / before.scale };
    const next = zoomAround(start, geo, 3, focal);
    const after = coverRect(geo, next);
    const sx = photoPt.x * after.scale + after.x - geo.winW / 2;
    const sy = photoPt.y * after.scale + after.y - geo.winH / 2;
    expect(sx).toBeCloseTo(focal.x, 4);
    expect(sy).toBeCloseTo(focal.y, 4);
    expect(next.zoom).toBe(3);
  });

  it('plainOrientation usa o quadro inteiro como janela', () => {
    expect(plainOrientation('landscape').window).toEqual({ x: 0, y: 0, w: 1920, h: 1080 });
    expect(plainOrientation('portrait').window).toEqual({ x: 0, y: 0, w: 1080, h: 1350 });
  });
});

describe('fitFontSize', () => {
  // medidor linear: cada caractere ocupa 0,6 * tamanho
  const measure = (t, s) => t.length * 0.6 * s;

  it('mantem o tamanho quando cabe', () => {
    expect(fitFontSize({ text: 'ABC', size: 72, maxWidth: 900, measure })).toBe(72);
  });

  it('reduz proporcionalmente quando passa de maxWidth', () => {
    const text = 'A'.repeat(40); // 40 * 0.6 * 72 = 1728 > 900
    const size = fitFontSize({ text, size: 72, maxWidth: 900, measure });
    expect(size).toBeLessThan(72);
    expect(measure(text, size)).toBeLessThanOrEqual(900);
    expect(size).toBeCloseTo((72 * 900) / 1728, 1);
  });

  it('refina quando a medida nao e linear e respeita o minimo', () => {
    const nonLinear = (t, s) => t.length * 0.6 * s + 30; // largura fixa extra
    const size = fitFontSize({ text: 'A'.repeat(40), size: 72, maxWidth: 900, measure: nonLinear });
    expect(nonLinear('A'.repeat(40), size)).toBeLessThanOrEqual(900);
    expect(fitFontSize({ text: 'A'.repeat(500), size: 72, maxWidth: 10, measure, minSize: 8 })).toBe(8);
  });

  it('texto vazio devolve o tamanho original', () => {
    expect(fitFontSize({ text: '', size: 50, maxWidth: 1, measure })).toBe(50);
  });
});
