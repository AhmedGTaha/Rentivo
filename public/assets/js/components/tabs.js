/**
 * Tab panels.
 *
 * Two kinds of tabs exist in Rentivo:
 *   - link tabs, which are ordinary anchors that reload with a query
 *     parameter (used wherever the tab changes what the server queries)
 *   - panel tabs, handled here, which switch between already-rendered panels
 */

export function initTabs(root = document) {
  root.querySelectorAll('[data-tabs]').forEach((container) => {
    if (container.dataset.tabsBound === '1') return;
    container.dataset.tabsBound = '1';

    const tabs = Array.from(container.querySelectorAll('[role="tab"]'));

    if (tabs.length === 0) return;

    const activate = (tab) => {
      tabs.forEach((candidate) => {
        const selected = candidate === tab;
        const panel = document.getElementById(candidate.getAttribute('aria-controls'));

        candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
        candidate.tabIndex = selected ? 0 : -1;

        if (panel) panel.hidden = !selected;
      });
    };

    tabs.forEach((tab) => {
      tab.addEventListener('click', (event) => {
        event.preventDefault();
        activate(tab);
      });

      // Standard roving-tabindex keyboard behaviour.
      tab.addEventListener('keydown', (event) => {
        const index = tabs.indexOf(tab);
        let target = null;

        if (event.key === 'ArrowRight') target = tabs[(index + 1) % tabs.length];
        if (event.key === 'ArrowLeft') target = tabs[(index - 1 + tabs.length) % tabs.length];
        if (event.key === 'Home') target = tabs[0];
        if (event.key === 'End') target = tabs[tabs.length - 1];

        if (!target) return;

        event.preventDefault();
        activate(target);
        target.focus();
      });
    });

    activate(tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') || tabs[0]);
  });
}

export default { initTabs };
