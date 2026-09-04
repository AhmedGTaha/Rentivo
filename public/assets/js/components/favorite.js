/**
 * Favorite (saved car) toggling.
 *
 * The button is always a real form posting to /favorites/{slug}/toggle with a
 * CSRF token. This module upgrades that to a fetch so the page does not
 * reload; if the fetch fails for any reason the form submits normally.
 */

import { toast } from './toast.js';

export function initFavorites(root = document) {
  root.querySelectorAll('form[data-favorite-form]').forEach((form) => {
    if (form.dataset.favoriteBound === '1') return;
    form.dataset.favoriteBound = '1';

    form.addEventListener('submit', async (event) => {
      const button = form.querySelector('[data-favorite-button]');

      if (!button) return;

      event.preventDefault();

      if (button.classList.contains('is-busy')) return;

      button.classList.add('is-busy');

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: new URLSearchParams(new FormData(form)),
          credentials: 'same-origin'
        });

        // 401 means the visitor is not signed in; fall back to the normal
        // submit so the server can send them through Google.
        if (response.status === 401 || response.redirected) {
          form.submit();
          return;
        }

        if (!response.ok) {
          throw new Error('Request failed');
        }

        const data = await response.json();

        button.setAttribute('aria-pressed', data.favorite ? 'true' : 'false');
        button.setAttribute(
          'aria-label',
          data.favorite ? 'Remove from saved cars' : 'Save this car'
        );

        toast(data.message, 'success', 2600);
        updateCounters(data.count);
      } catch (error) {
        // Any failure degrades to a full page submit.
        form.submit();
      } finally {
        button.classList.remove('is-busy');
      }
    });
  });
}

function updateCounters(count) {
  if (typeof count !== 'number') return;

  document.querySelectorAll('[data-favorite-count]').forEach((node) => {
    node.textContent = String(count);
  });
}

export default { initFavorites };
