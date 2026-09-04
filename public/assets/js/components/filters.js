/**
 * Browse filter behaviour.
 *
 * Filtering is entirely server-side and state lives in the query string; this
 * module only removes friction:
 *   - auto-submits the filter form when a select or checkbox changes
 *   - debounces the search box
 *   - keeps the pickup/return datetime pair sensible
 *
 * With JavaScript disabled the visible "Apply filters" button still submits
 * the same form, so nothing here is load-bearing.
 */

const SUBMIT_DELAY = 450;

export function initFilters(root = document) {
  root.querySelectorAll('[data-filter-form]').forEach((form) => {
    if (form.dataset.filterBound === '1') return;
    form.dataset.filterBound = '1';

    const submit = () => {
      // Returning to page 1 whenever a filter changes avoids landing on an
      // empty page of a now-smaller result set.
      const page = form.querySelector('input[name="page"]');
      if (page) page.value = '1';

      form.requestSubmit ? form.requestSubmit() : form.submit();
    };

    form.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach((control) => {
      control.addEventListener('change', submit);
    });

    let timer = null;

    form.querySelectorAll('input[type="search"], input[data-filter-debounce]').forEach((input) => {
      input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(submit, SUBMIT_DELAY);
      });
    });

    // Price and date inputs submit on blur rather than per keystroke.
    form.querySelectorAll('input[type="number"], input[type="datetime-local"]').forEach((input) => {
      input.addEventListener('change', submit);
    });
  });

  initDateRanges(root);
}

/**
 * Keeps a pickup/return pair ordered: the return field can never be set
 * earlier than the pickup field.
 */
export function initDateRanges(root = document) {
  root.querySelectorAll('[data-date-range]').forEach((group) => {
    if (group.dataset.rangeBound === '1') return;
    group.dataset.rangeBound = '1';

    const start = group.querySelector('[data-range-start]');
    const end = group.querySelector('[data-range-end]');

    if (!start || !end) return;

    const sync = () => {
      if (!start.value) return;

      end.min = start.value;

      if (end.value && end.value <= start.value) {
        // Default to a 24-hour rental when the range becomes invalid.
        const next = new Date(start.value);
        next.setDate(next.getDate() + 1);
        end.value = toLocalInput(next);
      }
    };

    start.addEventListener('change', sync);
    sync();
  });
}

function toLocalInput(date) {
  const pad = (value) => String(value).padStart(2, '0');

  return (
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
    `T${pad(date.getHours())}:${pad(date.getMinutes())}`
  );
}

export default { initFilters, initDateRanges };
