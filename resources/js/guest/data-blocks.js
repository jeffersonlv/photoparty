// Dados do servidor chegam em <script type="application/json" id="...">: blocos de dados
// nao executam, entao passam por CSP estrita (script-src 'self') sem 'unsafe-inline'.

export function readJsonBlock(id, fallback = {}) {
  const el = typeof document !== 'undefined' ? document.getElementById(id) : null;
  if (!el) return fallback;
  try {
    return JSON.parse(el.textContent || '');
  } catch {
    return fallback;
  }
}
