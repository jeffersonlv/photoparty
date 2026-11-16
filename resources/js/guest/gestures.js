// Arrastar (1 dedo/mouse) e pinca (2 dedos) para ajustar a foto dentro da janela.
// Trabalha em pointer events; teclado e roda do mouse como alternativas acessiveis.

import { clampView, zoomAround } from './frame-engine.js';

const KEY_STEP = 24; // px do design por toque de seta
const ZOOM_STEP = 1.1;

/**
 * @param {HTMLElement} el elemento tocavel (o canvas da previa)
 * @param {object} o
 * @param {() => {imgW:number,imgH:number,winW:number,winH:number,winX:number,winY:number,designW:number,designH:number}} o.getGeometry
 *   winX/winY: posicao da janela no quadro; designW/H: tamanho do quadro em px do design
 * @param {() => {zoom:number,panX:number,panY:number}} o.getView
 * @param {(view) => void} o.onChange recebe a view ja limitada
 * @returns {() => void} funcao para remover os listeners
 */
export function attachGestures(el, { getGeometry, getView, onChange }) {
  const pointers = new Map(); // id -> {x, y} em px CSS
  let lastDist = 0;
  let lastMid = null;

  // px CSS -> px do design (o canvas e exibido reduzido por CSS)
  const ratio = () => {
    const g = getGeometry();
    const box = el.getBoundingClientRect();
    return { k: box.width ? g.designW / box.width : 1, box, g };
  };
  const emit = (view) => onChange(clampView(view, getGeometry()));

  // ponto do ponteiro em relacao ao CENTRO da janela, em px do design
  const focalOf = (clientX, clientY) => {
    const { k, box, g } = ratio();
    return {
      x: (clientX - box.left) * k - (g.winX + g.winW / 2),
      y: (clientY - box.top) * k - (g.winY + g.winH / 2),
    };
  };

  const mid = () => {
    const [a, b] = [...pointers.values()];
    return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
  };
  const dist = () => {
    const [a, b] = [...pointers.values()];
    return Math.hypot(a.x - b.x, a.y - b.y);
  };

  function onDown(e) {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    el.setPointerCapture?.(e.pointerId);
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pointers.size === 2) {
      lastDist = dist();
      lastMid = mid();
    }
  }

  function onMove(e) {
    const prev = pointers.get(e.pointerId);
    if (!prev) return;
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    const view = getView();
    const { k } = ratio();

    if (pointers.size === 1) {
      emit({ ...view, panX: view.panX + (e.clientX - prev.x) * k, panY: view.panY + (e.clientY - prev.y) * k });
    } else if (pointers.size === 2) {
      const d = dist();
      const m = mid();
      if (lastDist > 0) {
        // zoom pelo fator da pinca em torno do ponto medio, somado ao arrasto do ponto medio
        const zoomed = zoomAround(view, getGeometry(), view.zoom * (d / lastDist), focalOf(m.x, m.y));
        emit({ ...zoomed, panX: zoomed.panX + (m.x - lastMid.x) * k, panY: zoomed.panY + (m.y - lastMid.y) * k });
      }
      lastDist = d;
      lastMid = m;
    }
    e.preventDefault();
  }

  function onUp(e) {
    pointers.delete(e.pointerId);
    lastDist = pointers.size === 2 ? dist() : 0;
    lastMid = pointers.size === 2 ? mid() : null;
  }

  function onWheel(e) {
    e.preventDefault();
    const view = getView();
    const factor = e.deltaY < 0 ? ZOOM_STEP : 1 / ZOOM_STEP;
    emit(zoomAround(view, getGeometry(), view.zoom * factor, focalOf(e.clientX, e.clientY)));
  }

  function onKey(e) {
    const view = getView();
    const move = {
      ArrowLeft: [KEY_STEP, 0], ArrowRight: [-KEY_STEP, 0], ArrowUp: [0, KEY_STEP], ArrowDown: [0, -KEY_STEP],
    }[e.key];
    if (move) {
      emit({ ...view, panX: view.panX + move[0], panY: view.panY + move[1] });
    } else if (e.key === '+' || e.key === '=') {
      emit(zoomAround(view, getGeometry(), view.zoom * ZOOM_STEP));
    } else if (e.key === '-' || e.key === '_') {
      emit(zoomAround(view, getGeometry(), view.zoom / ZOOM_STEP));
    } else {
      return;
    }
    e.preventDefault();
  }

  el.style.touchAction = 'none'; // sem rolagem/zoom da pagina durante o gesto
  el.addEventListener('pointerdown', onDown);
  el.addEventListener('pointermove', onMove);
  el.addEventListener('pointerup', onUp);
  el.addEventListener('pointercancel', onUp);
  el.addEventListener('pointerleave', onUp);
  el.addEventListener('wheel', onWheel, { passive: false });
  el.addEventListener('keydown', onKey);

  return () => {
    el.removeEventListener('pointerdown', onDown);
    el.removeEventListener('pointermove', onMove);
    el.removeEventListener('pointerup', onUp);
    el.removeEventListener('pointercancel', onUp);
    el.removeEventListener('pointerleave', onUp);
    el.removeEventListener('wheel', onWheel);
    el.removeEventListener('keydown', onKey);
    pointers.clear();
  };
}
