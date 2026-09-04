/**
 * Slide-in drawers, used for mobile filters and mobile management navigation.
 *
 * Markup contract:
 *   <button data-drawer-open="drawer-id" aria-expanded="false">
 *   <div class="drawer" id="drawer-id" hidden role="dialog" aria-modal="true">
 *     <div class="drawer__backdrop" data-overlay-dismiss></div>
 *     <div class="drawer__panel" data-overlay-panel> ... </div>
 *   </div>
 */

import { openOverlay, closeOverlay } from './overlay.js';

export function initDrawers(root = document) {
  root.querySelectorAll('[data-drawer-open]').forEach((trigger) => {
    if (trigger.dataset.drawerBound === '1') return;
    trigger.dataset.drawerBound = '1';

    trigger.addEventListener('click', (event) => {
      const drawer = document.getElementById(trigger.dataset.drawerOpen);

      if (!drawer) return;

      event.preventDefault();
      trigger.setAttribute('aria-expanded', 'true');
      openOverlay(drawer, { trigger });

      // Keep the trigger's expanded state honest when the drawer closes.
      const observer = new MutationObserver(() => {
        if (drawer.hidden) {
          trigger.setAttribute('aria-expanded', 'false');
          observer.disconnect();
        }
      });

      observer.observe(drawer, { attributes: true, attributeFilter: ['hidden'] });
    });
  });

  root.querySelectorAll('[data-drawer-close]').forEach((button) => {
    if (button.dataset.drawerBound === '1') return;
    button.dataset.drawerBound = '1';

    button.addEventListener('click', (event) => {
      const drawer = button.closest('.drawer');

      if (!drawer) return;

      event.preventDefault();
      closeOverlay(drawer);
    });
  });

  // A drawer that has grown irrelevant (e.g. the viewport widened past the
  // breakpoint where it is used) should not stay open.
  if (!initDrawers.resizeBound) {
    initDrawers.resizeBound = true;

    window.addEventListener('resize', () => {
      if (window.innerWidth < 1024) return;

      document.querySelectorAll('.drawer[data-close-on-desktop]:not([hidden])').forEach((drawer) => {
        closeOverlay(drawer);
      });
    });
  }
}

export default { initDrawers };
