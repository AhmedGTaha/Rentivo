/**
 * Modal dialogs, including the shared confirmation modal.
 *
 * Markup contract:
 *   <button data-modal-open="modal-id">
 *   <div class="modal" id="modal-id" hidden role="dialog" aria-modal="true">
 *     <div class="modal__backdrop" data-overlay-dismiss></div>
 *     <div class="modal__dialog" data-overlay-panel> ... </div>
 *   </div>
 *
 * Confirmation is progressive: a form carrying data-confirm submits normally
 * if JavaScript is unavailable, and shows the dialog when it is.
 */

import { openOverlay, closeOverlay } from './overlay.js';

export function initModals(root = document) {
  root.querySelectorAll('[data-modal-open]').forEach((trigger) => {
    if (trigger.dataset.modalBound === '1') return;
    trigger.dataset.modalBound = '1';

    trigger.addEventListener('click', (event) => {
      const modal = document.getElementById(trigger.dataset.modalOpen);

      if (!modal) return;

      event.preventDefault();
      openOverlay(modal, { trigger });
    });
  });

  root.querySelectorAll('[data-modal-close]').forEach((button) => {
    if (button.dataset.modalBound === '1') return;
    button.dataset.modalBound = '1';

    button.addEventListener('click', (event) => {
      const modal = button.closest('.modal');

      if (!modal) return;

      event.preventDefault();
      closeOverlay(modal);
    });
  });
}

/**
 * Intercepts submission of any form carrying data-confirm and asks first.
 */
export function initConfirmations(root = document) {
  root.querySelectorAll('form[data-confirm]').forEach((form) => {
    if (form.dataset.confirmBound === '1') return;
    form.dataset.confirmBound = '1';

    form.addEventListener('submit', (event) => {
      if (form.dataset.confirmed === '1') {
        return;
      }

      event.preventDefault();

      confirmAction({
        title: form.dataset.confirmTitle || 'Are you sure?',
        message: form.dataset.confirm,
        confirmLabel: form.dataset.confirmLabel || 'Confirm',
        destructive: form.dataset.confirmDestructive === '1'
      }).then((accepted) => {
        if (!accepted) return;

        form.dataset.confirmed = '1';
        form.submit();
      });
    });
  });
}

/**
 * Builds a confirmation dialog and resolves with the user's choice.
 *
 * @returns {Promise<boolean>}
 */
export function confirmAction({ title, message, confirmLabel = 'Confirm', destructive = false }) {
  return new Promise((resolve) => {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.hidden = true;

    modal.innerHTML = `
      <div class="modal__backdrop" data-overlay-dismiss></div>
      <div class="modal__dialog" data-overlay-panel role="document">
        <div class="modal__header">
          <h2 class="modal__title"></h2>
          <p class="modal__description"></p>
        </div>
        <div class="modal__actions">
          <button type="button" class="btn btn--secondary" data-choice="cancel">Cancel</button>
          <button type="button" class="btn ${destructive ? 'btn--danger' : ''}"
                  data-choice="confirm" data-autofocus></button>
        </div>
      </div>`;

    // Text is assigned, never interpolated, so messages cannot inject markup.
    modal.querySelector('.modal__title').textContent = title;
    modal.querySelector('.modal__description').textContent = message || '';
    modal.querySelector('[data-choice="confirm"]').textContent = confirmLabel;

    if (!message) {
      modal.querySelector('.modal__description').remove();
    }

    document.body.appendChild(modal);

    const finish = (result) => {
      closeOverlay(modal);
      modal.remove();
      resolve(result);
    };

    modal.querySelector('[data-choice="cancel"]').addEventListener('click', () => finish(false));
    modal.querySelector('[data-choice="confirm"]').addEventListener('click', () => finish(true));
    modal.querySelector('.modal__backdrop').addEventListener('click', () => finish(false));

    modal.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') finish(false);
    });

    openOverlay(modal);
  });
}

export default { initModals, initConfirmations, confirmAction };
