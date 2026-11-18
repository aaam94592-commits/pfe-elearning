// Small theme JS: subtle header animation, gradient pulse and dark-mode toggle
document.addEventListener('DOMContentLoaded', function(){
  if(document.fonts && document.fonts.load){
    document.fonts.load('900 16px "Font Awesome 6 Free"').then(function(fonts){
      if(!fonts.length){
        document.documentElement.classList.add('icons-unavailable');
      }
    }).catch(function(){
      document.documentElement.classList.add('icons-unavailable');
    });
  }

  document.querySelectorAll('img[data-fallback]').forEach(function(image){
    const useFallback = function(){
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

  try{
    const brand = document.querySelector('.site-brand .logo');
    if(brand){
      let t=0; setInterval(()=>{ t = (t+1) % 360; brand.style.transform = `rotate(${t/6}deg)`; }, 6000);
    }

    const toggle = document.getElementById('darkToggle');
    function setDark(enabled){
      if(enabled){ document.documentElement.classList.add('dark'); }
      else{ document.documentElement.classList.remove('dark'); }
      if(toggle){
        const icon = toggle.querySelector('i');
        if(icon){
          icon.classList.toggle('fa-moon', !enabled);
          icon.classList.toggle('fa-sun', enabled);
        }
        toggle.setAttribute('aria-label', enabled ? 'Activer le mode clair' : 'Activer le mode sombre');
        toggle.setAttribute('title', enabled ? 'Activer le mode clair' : 'Activer le mode sombre');
      }
      localStorage.setItem('darkMode', enabled ? '1' : '0');
    }

    // Initialize from saved preference
    const saved = localStorage.getItem('darkMode') === '1';
    setDark(saved);

    if(toggle) toggle.addEventListener('click', ()=> setDark(!document.documentElement.classList.contains('dark')));
  }catch(e){console.error(e)}
});