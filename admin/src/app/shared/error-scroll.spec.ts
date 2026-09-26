import { observeErrorMessages } from './error-scroll';

describe('admin error scrolling', () => {
  it('scrolls to an error that appears after saving', async () => {
    const host = document.createElement('div');
    const error = document.createElement('p');
    error.className = 'field-error';
    error.getClientRects = () => [error.getBoundingClientRect()] as unknown as DOMRectList;
    error.scrollIntoView = vi.fn();
    document.body.append(host);
    const stop = observeErrorMessages(host);

    host.append(error);
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

    expect(error.scrollIntoView).toHaveBeenCalledOnce();
    stop();
    host.remove();
  });
});
