/**
 * Shared overlay behaviour for modals and drawers.
 *
 * Both patterns need the same things — open/close, focus trapping, Escape to
 * dismiss, backdrop click, and restoring focus to the trigger — so that logic
 * lives here once and both components build on it.
 */

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])'
].join(',');

const openOverlays = [];

function focusableWithin(element) {
  return Array.from(element.querySelectorAll(FOCUSABLE)).filter(
    (node) => node.offsetParent !== null || node === document.activeElement
  );
}

function trapFocus(event, panel) {
  if (event.key !== 'Tab') return;

  const focusable = focusableWithin(panel);

  if (focusable.length === 0) {
    event.preventDefault();
    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

export function openOverlay(element, options = {}) {
  if (!element || !element.hidden === false) {
    // Already open.
  }

  const panel = element.querySelector('[data-overlay-panel]') || element;
  const trigger = options.trigger || document.activeElement;

  element.hidden = false;
  document.body.classList.add('has-overlay');

  const onKeydown = (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeOverlay(element);
      return;
    }

    trapFocus(event, panel);
  };

  const onPointerDown = (event) => {
    if (event.target.closest('[data-overlay-dismiss]')) {
      closeOverlay(element);
    }
  };

  element.addEventListener('keydown', onKeydown);
  element.addEventListener('click', onPointerDown);

  openOverlays.push({ element, trigger, onKeydown, onPointerDown });

  // Move focus into the overlay so keyboard users are not left behind it.
  const target = panel.querySelector('[data-autofocus]') || focusableWithin(panel)[0] || panel;

  if (target === panel && !panel.hasAttribute('tabindex')) {
    panel.setAttribute('tabindex', '-1');
  }

  window.requestAnimationFrame(() => target.focus({ preventScroll: true }));
}

export function closeOverlay(element) {
  const index = openOverlays.findIndex((entry) => entry.element === element);

  if (index === -1) {
    element.hidden = true;
    return;
  }

  const entry = openOverlays[index];

  element.removeEventListener('keydown', entry.onKeydown);
  element.removeEventListener('click', entry.onPointerDown);
  element.hidden = true;

  openOverlays.splice(index, 1);

  if (openOverlays.length === 0) {
    document.body.classList.remove('has-overlay');
  }

  if (entry.trigger && typeof entry.trigger.focus === 'function') {
    entry.trigger.focus({ preventScroll: true });
  }
}

export function isOpen(element) {
  return openOverlays.some((entry) => entry.element === element);
}

export default { openOverlay, closeOverlay, isOpen };
