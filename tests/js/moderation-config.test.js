import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

// Regressão: a tela de moderação lia `config.demo`, variável inexistente
// (a configuração da página se chama `cfg`). O erro quebrava a renderização das fotos.
describe('moderation/index.js', () => {
  const src = readFileSync(resolve(import.meta.dirname, '../../resources/js/moderation/index.js'), 'utf8');

  it('reads the page configuration only through cfg', () => {
    expect(src).toMatch(/const cfg = readJson\('pp-moderation'\)/);
    expect(src).not.toMatch(/(?<![\w.])config\./);
  });
});
