import { describe, expect, it } from 'vitest';
import {
  encodeUnderBudget, nextQuality, PHOTO_MAX_BYTES, PHOTO_QUALITY, THUMB_QUALITY, thumbnailSize,
} from '../../resources/js/guest/export.js';

describe('thumbnailSize', () => {
  it('lado maior = 400 px mantendo proporcao', () => {
    expect(thumbnailSize(1080, 1350)).toEqual({ width: 320, height: 400 });
    expect(thumbnailSize(1920, 1080)).toEqual({ width: 400, height: 225 });
  });
  it('nunca amplia', () => {
    expect(thumbnailSize(200, 100)).toEqual({ width: 200, height: 100 });
  });
});

describe('qualidade', () => {
  it('constantes do contrato', () => {
    expect(PHOTO_QUALITY).toBe(0.85);
    expect(THUMB_QUALITY).toBe(0.8);
    expect(PHOTO_MAX_BYTES).toBe(600 * 1024);
  });

  it('nextQuality desce em passos e para no minimo', () => {
    expect(nextQuality(0.85)).toBe(0.8);
    expect(nextQuality(0.55)).toBe(0.5);
    expect(nextQuality(0.5)).toBeNull();
  });

  it('nao reduz quando ja cabe', async () => {
    const calls = [];
    const r = await encodeUnderBudget(async (q) => { calls.push(q); return { size: 300 * 1024 }; }, { quality: 0.85, maxBytes: 600 * 1024 });
    expect(calls).toEqual([0.85]);
    expect(r.quality).toBe(0.85);
  });

  it('reduz a qualidade ate passar a caber em 600 KB', async () => {
    // tamanho proporcional a qualidade: q=0.85 -> 800 KB
    const encode = async (q) => ({ size: Math.round((q / 0.85) * 800 * 1024) });
    const r = await encodeUnderBudget(encode, { quality: 0.85, maxBytes: 600 * 1024 });
    expect(r.blob.size).toBeLessThanOrEqual(600 * 1024);
    expect(r.quality).toBeLessThan(0.85);
    expect(r.quality).toBeGreaterThanOrEqual(0.5);
  });

  it('desiste no minimo mesmo se ainda estiver grande', async () => {
    const r = await encodeUnderBudget(async () => ({ size: 5 * 1024 * 1024 }), { quality: 0.85, maxBytes: 600 * 1024 });
    expect(r.quality).toBe(0.5);
  });
});
