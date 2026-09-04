/**
 * Car detail image gallery.
 *
 * The gallery is progressive: every image is rendered server-side and the
 * first is visible without JavaScript. This module only adds navigation.
 */

export function initGalleries(root = document) {
  root.querySelectorAll('[data-gallery]').forEach((gallery) => {
    if (gallery.dataset.galleryBound === '1') return;
    gallery.dataset.galleryBound = '1';

    const images = Array.from(gallery.querySelectorAll('[data-gallery-image]'));
    const thumbs = Array.from(gallery.querySelectorAll('[data-gallery-thumb]'));
    const counter = gallery.querySelector('[data-gallery-counter]');
    const previous = gallery.querySelector('[data-gallery-prev]');
    const next = gallery.querySelector('[data-gallery-next]');

    if (images.length === 0) return;

    let index = 0;

    const show = (target) => {
      index = (target + images.length) % images.length;

      images.forEach((image, position) => {
        image.hidden = position !== index;
      });

      thumbs.forEach((thumb, position) => {
        thumb.setAttribute('aria-current', position === index ? 'true' : 'false');
      });

      if (counter) {
        counter.textContent = `${index + 1} / ${images.length}`;
      }
    };

    thumbs.forEach((thumb, position) => {
      thumb.addEventListener('click', () => show(position));
    });

    if (previous) previous.addEventListener('click', () => show(index - 1));
    if (next) next.addEventListener('click', () => show(index + 1));

    // Arrow keys work whenever the gallery holds focus.
    gallery.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        show(index - 1);
      } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        show(index + 1);
      }
    });

    show(0);
  });
}

export default { initGalleries };
