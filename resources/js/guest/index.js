// Ponto de entrada de producao: le os blocos JSON, registra o componente Alpine e inicia.
// Usa o build CSP do Alpine (sem eval/new Function) e nenhum CDN.

import Alpine from '@alpinejs/csp';
import { readJsonBlock } from './data-blocks.js';
import { registerGuestApp } from './guest-app.js';

window.PHOTO_PARTY_EVENT = readJsonBlock('pp-event');
window.PHOTO_PARTY_I18N = readJsonBlock('pp-i18n');

// Tela unica: altura real visivel (inclui teclado virtual aberto) em --gp-app-h.
// CSSOM (style.setProperty) e permitido pela CSP; nenhum estilo inline no HTML.
if (window.visualViewport) {
  const root = document.documentElement;
  const sync = () => root.style.setProperty('--gp-app-h', `${Math.round(window.visualViewport.height)}px`);
  sync();
  root.classList.add('gp-has-vvh');
  window.visualViewport.addEventListener('resize', sync);
}

window.Alpine = Alpine;
registerGuestApp(Alpine);
Alpine.start();
