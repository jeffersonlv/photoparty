import { describe, expect, it } from 'vitest';
import { buildConstraints, classifyCameraError, shouldMirror } from '../../resources/js/guest/camera.js';

describe('camera', () => {
  it('classifica erros do getUserMedia', () => {
    expect(classifyCameraError({ name: 'NotAllowedError' })).toBe('denied');
    expect(classifyCameraError({ name: 'NotFoundError' })).toBe('notfound');
    expect(classifyCameraError({ name: 'NotReadableError' })).toBe('busy');
    expect(classifyCameraError(new Error('x'))).toBe('unknown');
    expect(classifyCameraError(undefined)).toBe('unknown');
  });

  it('traseira por padrao, frontal sob demanda', () => {
    expect(buildConstraints().video.facingMode.ideal).toBe('environment');
    expect(buildConstraints('user').video.facingMode.ideal).toBe('user');
    expect(buildConstraints().audio).toBe(false);
  });

  it('so a frontal e espelhada', () => {
    expect(shouldMirror('user')).toBe(true);
    expect(shouldMirror('environment')).toBe(false);
  });
});
