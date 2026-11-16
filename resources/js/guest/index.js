// Ponto de entrada de producao: le os blocos JSON, registra o componente Alpine e inicia.
// Usa o build CSP do Alpine (sem eval/new Function) e nenhum CDN.

import Alpine from '@alpinejs/csp';
import { readJsonBlock } from './data-blocks.js';
import { registerGuestApp } from './guest-app.js';

window.PHOTO_PARTY_EVENT = readJsonBlock('pp-event');
window.PHOTO_PARTY_I18N = readJsonBlock('pp-i18n');

window.Alpine = Alpine;
registerGuestApp(Alpine);
Alpine.start();
