import '../resources/css/guest.css';
import Alpine from '@alpinejs/csp';
import { registerGuestApp } from '../resources/js/guest/guest-app.js';
import { readJsonBlock } from '../resources/js/guest/data-blocks.js';
import { mockUpload } from './mock-upload.js';

const params = new URLSearchParams(location.search);

window.PHOTO_PARTY_I18N = readJsonBlock('pp-i18n');

// Mesmo formato que o Laravel entrega em window.PHOTO_PARTY_EVENT
window.PHOTO_PARTY_EVENT = {
  name: 'Festa Demo',
  welcome: 'Tire uma foto, escolha uma moldura e apareça no telão.',
  moderation: params.get('moderation') !== '0',
  language: 'pt_BR',
  askGuestName: true,
  uploadUrl: '/api/mock-upload',
  frames: [
    { key: 'test_led', name: 'Painel de LED', configUrl: '/frames/test_led/config.json', texts: { event_name: 'Festa Demo' } },
    { key: 'test_sepia', name: 'Cartaz sépia', configUrl: '/frames/test_sepia/config.json' },
  ],
};

// Registra o resultado do "envio" na pagina para conferir tamanhos e campos
window.addEventListener('demo:upload', (e) => {
  const { photo, thumb, fields } = e.detail;
  const li = document.createElement('li');
  const kb = (b) => `${(b.size / 1024).toFixed(0)} KB`;
  li.textContent = `foto ${kb(photo)} (${photo.type}), miniatura ${kb(thumb)}; ${JSON.stringify(fields)} `;
  const a = document.createElement('a');
  a.href = URL.createObjectURL(photo);
  a.textContent = 'abrir foto';
  a.target = '_blank';
  a.rel = 'noopener';
  li.append(a);
  document.getElementById('demo-log-list').append(li);
});

window.Alpine = Alpine;
registerGuestApp(Alpine, { uploadPhoto: mockUpload });
Alpine.start();
