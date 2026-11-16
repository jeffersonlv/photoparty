// Lê e normaliza o bloco <script type="application/json" id="pp-screen">.

export const TRANSITIONS = ['fade', 'slide', 'zoom', 'none'];

export function parseConfig(raw) {
  let data = {};

  try {
    data = JSON.parse(raw) ?? {};
  } catch {
    data = {};
  }

  const seconds = Number.parseInt(data.secondsPerPhoto, 10);

  return {
    pollUrl: typeof data.pollUrl === 'string' ? data.pollUrl : '',
    secondsPerPhoto: Number.isFinite(seconds) ? Math.min(60, Math.max(3, seconds)) : 6,
    transition: TRANSITIONS.includes(data.transition) ? data.transition : 'fade',
    status: typeof data.status === 'string' ? data.status : 'live',
    pollMs: 5000,
  };
}
