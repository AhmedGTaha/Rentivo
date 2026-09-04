/**
 * Rentivo front-end entry point.
 *
 * Every page is fully functional server-rendered HTML; this module only layers
 * on progressive enhancements. Nothing here is required for a page to work.
 */

import { initModals, initConfirmations } from './components/modal.js';
import { initDrawers } from './components/drawer.js';
import { initDropdowns } from './components/dropdown.js';
import { initGalleries } from './components/gallery.js';
import { initFilters } from './components/filters.js';
import { initTabs } from './components/tabs.js';
import { initFavorites } from './components/favorite.js';
import { initImageUploads } from './components/image-upload.js';
import { toast } from './components/toast.js';

function initDismissibleAlerts(root = document) {
  root.querySelectorAll('[data-alert-dismiss]').forEach((button) => {
    if (button.dataset.alertBound === '1') return;
    button.dataset.alertBound = '1';

    button.addEventListener('click', () => {
      const alert = button.closest('.alert');
      if (alert) alert.remove();
    });
  });
}

/**
 * Marks a submitting form's button as busy so a slow request cannot be
 * double-submitted (which matters for booking creation in particular).
 */
function initSubmitGuards(root = document) {
  root.querySelectorAll('form[data-guard-submit]').forEach((form) => {
    if (form.dataset.guardBound === '1') return;
    form.dataset.guardBound = '1';

    form.addEventListener('submit', () => {
      const button = form.querySelector('[type="submit"]');

      if (!button || button.disabled) return;

      // Deferred so the button's value is still included in the submission.
      window.setTimeout(() => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
      }, 0);
    });
  });
}

/** Copy-to-clipboard for invitation links shown in local development. */
function initCopyButtons(root = document) {
  root.querySelectorAll('[data-copy]').forEach((button) => {
    if (button.dataset.copyBound === '1') return;
    button.dataset.copyBound = '1';

    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(button.dataset.copy);
        toast('Copied to clipboard.', 'success', 2000);
      } catch (error) {
        toast('Copy failed. Select the text manually.', 'warning');
      }
    });
  });
}

export function init(root = document) {
  initModals(root);
  initConfirmations(root);
  initDrawers(root);
  initDropdowns(root);
  initGalleries(root);
  initFilters(root);
  initTabs(root);
  initFavorites(root);
  initImageUploads(root);
  initDismissibleAlerts(root);
  initSubmitGuards(root);
  initCopyButtons(root);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => init());
} else {
  init();
}

// Exposed for the component gallery, which re-initialises rendered examples.
window.Rentivo = { init, toast };
