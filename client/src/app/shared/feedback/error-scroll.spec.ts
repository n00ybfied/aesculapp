import { observeErrorMessages, scrollToFirstError } from './error-scroll';

describe('error scrolling', () => {
  it('scrolls to the first visible error in document order', () => {
    const host = document.createElement('div');
    const first = document.createElement('p');
    const second = document.createElement('p');
    first.setAttribute('role', 'alert');
    second.setAttribute('role', 'alert');
    host.append(first, second);
    document.body.append(host);
    first.getClientRects = () => [first.getBoundingClientRect()] as unknown as DOMRectList;
    second.getClientRects = () => [second.getBoundingClientRect()] as unknown as DOMRectList;
    const firstScroll = vi.fn();
    const secondScroll = vi.fn();
    first.scrollIntoView = firstScroll;
    second.scrollIntoView = secondScroll;

    scrollToFirstError([second, first]);

    expect(firstScroll).toHaveBeenCalledOnce();
    expect(secondScroll).not.toHaveBeenCalled();
    host.remove();
  });

  it('detects a newly displayed validation error inside a dialog', async () => {
    const host = document.createElement('div');
    const dialog = document.createElement('div');
    dialog.setAttribute('role', 'dialog');
    const error = document.createElement('p');
    error.setAttribute('role', 'alert');
    error.getClientRects = () => [error.getBoundingClientRect()] as unknown as DOMRectList;
    error.scrollIntoView = vi.fn();
    dialog.append(error);
    document.body.append(host);
    const stop = observeErrorMessages(host);

    host.append(dialog);
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

    expect(error.scrollIntoView).toHaveBeenCalledOnce();
    stop();
    host.remove();
  });
});
