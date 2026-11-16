// Le um arquivo de traducao do Laravel (array PHP plano de strings com aspas simples).
// Usado pela demo e pelos testes para manter UMA fonte de verdade: lang/*/guest.php.
import { readFileSync } from 'node:fs';

export function parsePhpLang(path) {
  const src = readFileSync(path, 'utf8');
  const out = {};
  const re = /^\s*'([a-z0-9_]+)'\s*=>\s*'((?:[^'\\]|\\.)*)'\s*,/gm;
  for (const m of src.matchAll(re)) out[m[1]] = m[2].replace(/\\(['\\])/g, '$1');
  return out;
}

/** Renderiza o parcial Blade (so {{-- --}} e {{ __('guest.x') }}) para HTML estatico. */
export function renderPartial(blade, dict) {
  const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  return blade
    .replace(/\{\{--[\s\S]*?--\}\}/g, '')
    .replace(/\{\{\s*__\('guest\.([a-z0-9_]+)'\)\s*\}\}/g, (_, k) => esc(dict[k] ?? k));
}
