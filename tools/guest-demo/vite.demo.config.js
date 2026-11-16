// Demo estatica: usa os mesmos modulos e o mesmo parcial Blade, sem backend.
import { defineConfig } from 'vite';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parsePhpLang, renderPartial } from './scripts/lang.mjs';

const root = resolve(import.meta.dirname);

// Injeta o markup real (parcial Blade) e as traducoes pt_BR no HTML da demo
function bladePartial() {
  return {
    name: 'photo-party-blade-partial',
    transformIndexHtml: {
      order: 'pre',
      handler(html) {
        const dict = parsePhpLang(resolve(root, 'lang/pt_BR/guest.php'));
        const partial = readFileSync(resolve(root, 'resources/views/guest/partials/app.blade.php'), 'utf8');
        return html
          .replace('<!--@gp-app-->', renderPartial(partial, dict))
          .replace('<!--@gp-i18n-->', `<script type="application/json" id="pp-i18n">${JSON.stringify(dict).replace(/</g, '\\u003C')}</script>`);
      },
    },
    handleHotUpdate({ file, server }) {
      if (file.endsWith('.blade.php') || file.endsWith('guest.php')) server.ws.send({ type: 'full-reload' });
    },
  };
}

export default defineConfig({
  root: resolve(root, 'demo'),
  publicDir: resolve(root, 'demo/public'),
  plugins: [bladePartial()],
  server: { fs: { allow: [root] }, host: true },
  build: { outDir: resolve(root, 'dist/demo'), emptyOutDir: true },
});
