/**
 * File upload affordances: drag-and-drop highlighting and a readable summary
 * of what has been selected.
 *
 * Validation is entirely server-side (MIME sniffing, image decoding, size
 * limits); anything checked here is convenience only.
 */

export function initImageUploads(root = document) {
  root.querySelectorAll('[data-file-upload]').forEach((zone) => {
    if (zone.dataset.uploadBound === '1') return;
    zone.dataset.uploadBound = '1';

    const input = zone.querySelector('input[type="file"]');
    const summary = zone.querySelector('[data-file-summary]');

    if (!input) return;

    const describe = () => {
      if (!summary) return;

      const files = Array.from(input.files || []);

      if (files.length === 0) {
        summary.textContent = '';
        return;
      }

      if (files.length === 1) {
        summary.textContent = `${files[0].name} (${formatBytes(files[0].size)})`;
        return;
      }

      const total = files.reduce((sum, file) => sum + file.size, 0);
      summary.textContent = `${files.length} files selected (${formatBytes(total)})`;
    };

    input.addEventListener('change', describe);

    ['dragenter', 'dragover'].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        zone.classList.add('is-dragging');
      });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        zone.classList.remove('is-dragging');
      });
    });

    zone.addEventListener('drop', (event) => {
      const files = event.dataTransfer?.files;

      if (!files || files.length === 0) return;

      input.files = files;
      describe();
    });
  });
}

function formatBytes(bytes) {
  if (bytes >= 1024 * 1024) {
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  }

  return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

export default { initImageUploads };
