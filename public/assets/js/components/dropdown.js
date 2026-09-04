/**
 * Dropdown menus (account menu, organization switcher, row actions).
 *
 * Markup contract:
 *   <div class="dropdown" data-dropdown>
 *     <button data-dropdown-toggle aria-expanded="false" aria-haspopup="true">
 *     <div class="dropdown__menu" role="menu"> ... </div>
 *   </div>
 */

function closeAll(except = null) {
  document.querySelectorAll('[data-dropdown].is-open').forEach((dropdown) => {
    if (dropdown === except) return;

    dropdown.classList.remove('is-open');
    const toggle = dropdown.querySelector('[data-dropdown-toggle]');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  });
}

export function initDropdowns(root = document) {
  root.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
    if (dropdown.dataset.dropdownBound === '1') return;
    dropdown.dataset.dropdownBound = '1';

    const toggle = dropdown.querySelector('[data-dropdown-toggle]');
    const menu = dropdown.querySelector('.dropdown__menu');

    if (!toggle || !menu) return;

    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-haspopup', 'true');

    toggle.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      const willOpen = !dropdown.classList.contains('is-open');
      closeAll(dropdown);

      dropdown.classList.toggle('is-open', willOpen);
      toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

      if (willOpen) {
        const first = menu.querySelector('a, button');
        if (first) window.requestAnimationFrame(() => first.focus());
      }
    });

    dropdown.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;

      dropdown.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus();
    });

    // Closing on blur keeps the menu from lingering after tabbing away.
    dropdown.addEventListener('focusout', (event) => {
      if (!dropdown.contains(event.relatedTarget)) {
        dropdown.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  });

  if (!initDropdowns.documentBound) {
    initDropdowns.documentBound = true;

    document.addEventListener('click', (event) => {
      if (!event.target.closest('[data-dropdown]')) {
        closeAll();
      }
    });
  }
}

export default { initDropdowns };
