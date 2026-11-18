import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
  applyOpsToPixels, enabledFilterKeys, FILTER_ALIASES, FILTER_KEYS, FILTERS, ledGrid, opMatrix, opsToCss, sepiaOps,
} from '../../resources/js/guest/effects.js';

const px = (r, g, b) => new Uint8ClampedArray([r, g, b, 255]);

describe('filtros', () => {
  it('tem os filtros pedidos', () => {
    expect(FILTER_KEYS).toEqual(['none', 'bw', 'vintage', 'vivid', 'cool', 'warm']);
  });

  it('usa exatamente as chaves de config/photoparty.php (lista canonica do servidor)', () => {
    const php = readFileSync(new URL('../../config/photoparty.php', import.meta.url), 'utf8');
    const match = php.match(/'filters'\s*=>\s*\[([^\]]*)\]/);
    const server = [...match[1].matchAll(/'([a-z]+)'/g)].map((m) => m[1]);
    expect(FILTER_KEYS).toEqual(server);
    const alias = php.match(/'filter_aliases'\s*=>\s*\[\s*'([a-z]+)'\s*=>\s*'([a-z]+)'/);
    expect(FILTER_ALIASES).toEqual({ [alias[1]]: alias[2] });
  });

  it('sepia e so efeito de moldura, nao filtro do convidado', () => {
    expect(FILTER_KEYS).not.toContain('sepia');
    expect(FILTERS.sepia).toBeUndefined();
  });

  it('enabledFilterKeys: ativos do evento, ordem canonica, sempre com none, alias sepia => vintage', () => {
    expect(enabledFilterKeys(undefined)).toEqual(FILTER_KEYS);
    expect(enabledFilterKeys([])).toEqual(['none']);
    expect(enabledFilterKeys(['warm', 'bw', 'hack'])).toEqual(['none', 'bw', 'warm']);
    expect(enabledFilterKeys(['sepia', 'vintage'])).toEqual(['none', 'vintage']);
  });

  it('opsToCss gera a string de ctx.filter', () => {
    expect(opsToCss(FILTERS.none)).toBe('');
    expect(opsToCss([{ fn: 'grayscale', v: 1 }, { fn: 'hue-rotate', v: -12 }])).toBe('grayscale(1) hue-rotate(-12deg)');
  });

  it('fallback manual: grayscale iguala os canais e preserva o alfa', () => {
    const d = applyOpsToPixels(px(255, 0, 0), [{ fn: 'grayscale', v: 1 }]);
    expect(d[0]).toBe(d[1]);
    expect(d[1]).toBe(d[2]);
    expect(d[0]).toBe(54); // 0,2126 * 255
    expect(d[3]).toBe(255);
  });

  it('matrizes de amount neutro sao identidade', () => {
    for (const op of [{ fn: 'grayscale', v: 0 }, { fn: 'sepia', v: 0 }, { fn: 'saturate', v: 1 }, { fn: 'hue-rotate', v: 0 }]) {
      const m = opMatrix(op);
      [1, 0, 0, 0, 1, 0, 0, 0, 1].forEach((v, i) => expect(m[i]).toBeCloseTo(v, 6));
    }
  });

  it('contrast e brightness lineares', () => {
    expect(Array.from(applyOpsToPixels(px(100, 100, 100), [{ fn: 'brightness', v: 2 }])).slice(0, 3)).toEqual([200, 200, 200]);
    // contrast 1 = identidade; contrast 2 em 128 mantem 128 (ponto fixo)
    expect(Array.from(applyOpsToPixels(px(128, 128, 128), [{ fn: 'contrast', v: 2 }])).slice(0, 3)).toEqual([128, 128, 128]);
  });

  it('sepia deixa a imagem quente (R >= G >= B)', () => {
    const d = applyOpsToPixels(px(120, 120, 120), sepiaOps({ amount: 1 }));
    expect(d[0]).toBeGreaterThan(d[1]);
    expect(d[1]).toBeGreaterThan(d[2]);
  });

  it('saturacao e clamp: valores nao estouram 0..255', () => {
    const d = applyOpsToPixels(px(250, 10, 10), [{ fn: 'saturate', v: 3 }, { fn: 'brightness', v: 3 }]);
    expect(Math.max(...d.slice(0, 3))).toBeLessThanOrEqual(255);
    expect(Math.min(...d.slice(0, 3))).toBeGreaterThanOrEqual(0);
  });
});

describe('ledGrid', () => {
  it('passo = (ponto + espaco) * escala e celulas cobrem a janela', () => {
    expect(ledGrid(840, 620, 6, 2, 1)).toEqual({ pitch: 8, cols: 105, rows: 78 });
    const half = ledGrid(420, 310, 6, 2, 0.5);
    expect(half.pitch).toBe(4);
    expect(half.cols).toBe(105);
  });
  it('arredonda para cima e tem minimos seguros', () => {
    expect(ledGrid(10, 10, 6, 2, 1)).toEqual({ pitch: 8, cols: 2, rows: 2 });
    expect(ledGrid(1, 1, 0, 0, 1).pitch).toBe(2);
  });
});
