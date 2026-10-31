/* Ushine Home v4 interactions. No dependencies; jQuery only if WooCommerce loaded it. */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Header shadow once scrolled; mobile menu. */
  var header = document.querySelector('[data-header]');
  var onScroll = function () { if (header) header.classList.toggle('is-scrolled', window.scrollY > 10); };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  var burger = document.querySelector('[data-burger]');
  var menu = document.querySelector('[data-menu]');
  if (burger && menu) {
    var toggle = function (open) {
      burger.setAttribute('aria-expanded', String(open));
      menu.hidden = !open;
      document.documentElement.style.overflow = open ? 'hidden' : '';
    };
    burger.addEventListener('click', function () { toggle(burger.getAttribute('aria-expanded') !== 'true'); });
    menu.addEventListener('click', function (e) { if (e.target.closest('a')) toggle(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { toggle(false); burger.focus(); } });
  }

  /* Rails: arrow buttons + mouse drag. Touch scrolls natively. */
  document.querySelectorAll('[data-rail]').forEach(function (rail) {
    var key = rail.getAttribute('data-rail');
    var prev = document.querySelector('[data-rail-prev="' + key + '"]');
    var next = document.querySelector('[data-rail-next="' + key + '"]');
    var step = function () { var c = rail.firstElementChild; return c ? (c.getBoundingClientRect().width + 16) * 2 : 300; };
    var sync = function () {
      if (prev) prev.disabled = rail.scrollLeft < 8;
      if (next) next.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 8;
    };
    if (prev) prev.addEventListener('click', function () { rail.scrollBy({ left: -step(), behavior: reduce ? 'auto' : 'smooth' }); });
    if (next) next.addEventListener('click', function () { rail.scrollBy({ left: step(), behavior: reduce ? 'auto' : 'smooth' }); });
    rail.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);
    sync();

    var down = false, sx = 0, sl = 0, moved = false;
    rail.addEventListener('pointerdown', function (e) {
      if (e.pointerType !== 'mouse') return;
      down = true; moved = false; sx = e.clientX; sl = rail.scrollLeft;
      rail.style.scrollSnapType = 'none';
    });
    window.addEventListener('pointermove', function (e) {
      if (!down) return;
      var dx = e.clientX - sx;
      if (Math.abs(dx) > 4) moved = true;
      rail.scrollLeft = sl - dx;
    });
    window.addEventListener('pointerup', function () { if (!down) return; down = false; rail.style.scrollSnapType = ''; });
    rail.addEventListener('click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); } }, true);
    rail.addEventListener('dragstart', function (e) { e.preventDefault(); });
  });

  /* Before/after compare. */
  var cmp = document.querySelector('[data-cmp]');
  if (cmp) {
    var range = cmp.querySelector('[data-cmp-range]');
    var slides = cmp.querySelectorAll('.u4-cmp__slide');
    var idxEl = document.querySelector('[data-cmp-index]');
    var labelEl = document.querySelector('[data-cmp-label]');
    var current = 0;
    var setPos = function (v) { cmp.style.setProperty('--pos', v + '%'); };
    range.addEventListener('input', function () { cmp.classList.remove('is-hint'); setPos(range.value); });
    var show = function (n) {
      current = (n + slides.length) % slides.length;
      slides.forEach(function (s, i) { s.classList.toggle('is-on', i === current); });
      if (idxEl) idxEl.textContent = String(current + 1);
      if (labelEl) labelEl.textContent = slides[current].getAttribute('data-label');
      range.value = 50; setPos(50);
    };
    var p = document.querySelector('[data-cmp-prev]');
    var n = document.querySelector('[data-cmp-next]');
    if (p) p.addEventListener('click', function () { show(current - 1); });
    if (n) n.addEventListener('click', function () { show(current + 1); });
    if ('IntersectionObserver' in window && !reduce) {
      var hint = new IntersectionObserver(function (en) {
        if (!en[0].isIntersecting) return;
        hint.disconnect();
        cmp.classList.add('is-hint');
        setTimeout(function () { setPos(28); }, 300);
        setTimeout(function () { setPos(72); }, 1200);
        setTimeout(function () { setPos(50); }, 2100);
        setTimeout(function () { cmp.classList.remove('is-hint'); }, 3100);
      }, { threshold: 0.6 });
      hint.observe(cmp);
    }
  }

  /* Add to cart: button state + toast + cart badge. */
  var toast = document.querySelector('[data-toast]');
  var tt;
  var cartUrl = (window.wc_add_to_cart_params && window.wc_add_to_cart_params.cart_url) || '/cart/';
  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart', function (e, f, h, $btn) {
      var btn = $btn && $btn[0];
      var card = btn && btn.closest ? btn.closest('.ush-card') : null;
      var name = card && card.querySelector('.ush-card__name') ? card.querySelector('.ush-card__name').textContent.trim() : '';
      if (btn) {
        var label = btn.querySelector('span');
        if (label) { label.textContent = 'Added'; setTimeout(function () { label.textContent = 'Add to cart'; btn.classList.remove('added'); }, 2200); }
      }
      if (toast) {
        toast.innerHTML = (name ? name + ' added to your cart.' : 'Added to your cart.') + '<a href="' + cartUrl + '">View cart</a>';
        toast.classList.add('is-on'); clearTimeout(tt); tt = setTimeout(function () { toast.classList.remove('is-on'); }, 3600);
      }
      var badge = document.querySelector('.ush-cart-count');
      if (badge) { badge.classList.remove('is-bump'); void badge.offsetWidth; badge.classList.add('is-bump'); }
    });
  }
})();
