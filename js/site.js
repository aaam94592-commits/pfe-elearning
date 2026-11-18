document.addEventListener('DOMContentLoaded', function () {
  if (document.fonts && document.fonts.load) {
    document.fonts.load('900 16px "Font Awesome 6 Free"').then(function (fonts) {
      if (!fonts.length) {
        document.documentElement.classList.add('icons-unavailable');
      }
    }).catch(function () {
      document.documentElement.classList.add('icons-unavailable');
    });
  }

  document.querySelectorAll('img[data-fallback]').forEach(function (image) {
    const useFallback = function () {
      const fallback = image.dataset.fallback;
      if (fallback && image.src !== new URL(fallback, document.baseURI).href) {
        image.src = fallback;
      }
    };
    image.addEventListener('error', useFallback, { once: true });
    if (image.complete && image.naturalWidth === 0) {
      useFallback();
    }
  });

  const revealItems = document.querySelectorAll('[data-reveal]');

  if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  document.body.classList.add('reveal-ready');

  const revealObserver = new IntersectionObserver(function (entries, observer) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });

  revealItems.forEach(function (item) {
    revealObserver.observe(item);
  });
});
