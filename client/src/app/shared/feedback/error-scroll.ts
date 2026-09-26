const errorSelector = '.field-error, [role="alert"]';

function visibleErrors(errors: Iterable<HTMLElement>): HTMLElement[] {
  return Array.from(errors).filter((error) =>
    error.isConnected &&
    error.getClientRects().length > 0 &&
    !error.closest('[hidden], [inert], [aria-hidden="true"]'),
  );
}

export function scrollToFirstError(errors: Iterable<HTMLElement>): void {
  const first = visibleErrors(errors).sort((left, right) =>
    left.compareDocumentPosition(right) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1,
  )[0];
  if (!first) return;

  first.scrollIntoView({
    block: 'center',
    behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
  });
}

export function observeErrorMessages(host: HTMLElement): () => void {
  const pending = new Set<HTMLElement>();
  let animationFrame = 0;
  const queue = (error: HTMLElement): void => {
    pending.add(error);
    if (animationFrame) return;
    animationFrame = requestAnimationFrame(() => {
      animationFrame = 0;
      scrollToFirstError(pending);
      pending.clear();
    });
  };
  const collect = (element: HTMLElement): void => {
    if (element.matches(errorSelector)) queue(element);
    element.querySelectorAll<HTMLElement>(errorSelector).forEach(queue);
  };
  const observer = new MutationObserver((records) => {
    for (const record of records) {
      if (record.type === 'characterData') {
        const error = record.target.parentElement?.closest<HTMLElement>(errorSelector);
        if (error) queue(error);
      }
      for (const node of record.addedNodes) {
        if (node instanceof HTMLElement) collect(node);
      }
    }
  });
  observer.observe(host, { childList: true, characterData: true, subtree: true });

  const onSubmit = (event: Event): void => {
    if (!(event.target instanceof HTMLFormElement)) return;
    const scope = event.target.closest('[role="dialog"]') ?? event.target;
    requestAnimationFrame(() => scrollToFirstError(scope.querySelectorAll<HTMLElement>(errorSelector)));
  };
  host.addEventListener('submit', onSubmit, true);

  return () => {
    observer.disconnect();
    host.removeEventListener('submit', onSubmit, true);
    if (animationFrame) cancelAnimationFrame(animationFrame);
  };
}
