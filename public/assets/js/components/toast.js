/**
 * Toast notifications.
 *
 * Server-rendered flash messages are the primary feedback channel; toasts are
 * used for actions that complete without a page load (favorites, reordering).
 */

const REGION_ID = 'toast-region';

function region() {
  let node = document.getElementById(REGION_ID);

  if (!node) {
    node = document.createElement('div');
    node.id = REGION_ID;
    node.className = 'toast-region';
    // Announced politely so screen readers are not interrupted mid-task.
    node.setAttribute('role', 'status');
    node.setAttribute('aria-live', 'polite');
    document.body.appendChild(node);
  }

  return node;
}

const ICONS = {
  success: '<path d="M20 6 9 17l-5-5"/>',
  error: '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
  warning: '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>'
};

export function toast(message, type = 'info', timeout = 4500) {
  if (!message) return null;

  const node = document.createElement('div');
  node.className = `toast toast--${type}`;

  node.innerHTML = `
    <svg class="toast__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      ${ICONS[type] || ICONS.info}
    </svg>
    <div class="toast__body"></div>
    <button type="button" class="toast__dismiss" aria-label="Dismiss notification">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>`;

  // textContent, never innerHTML, so a server message can never inject markup.
  node.querySelector('.toast__body').textContent = message;

  const dismiss = () => {
    node.classList.add('is-leaving');
    node.addEventListener('animationend', () => node.remove(), { once: true });
    // Fallback if the animation is disabled by reduced-motion settings.
    setTimeout(() => node.remove(), 400);
  };

  node.querySelector('.toast__dismiss').addEventListener('click', dismiss);

  region().appendChild(node);

  if (timeout > 0) {
    setTimeout(dismiss, timeout);
  }

  return node;
}

export default { toast };
