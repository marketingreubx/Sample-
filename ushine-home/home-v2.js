/* Ushine Home v2 interactions. No dependencies; jQuery is used only if WooCommerce loaded it. */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.documentElement.classList.add('ub-js');

  /* Header: transparent over the hero, solid once the page scrolls. */
  var header = document.querySelector('[data-header]');
  var burger = document.querySelector('[data-burger]');
  var drawer = document.querySelector('[data-drawer]');
  var cta = document.querySelector('[data-parallax-y] img');
  var ticking = false;
  var onScroll = function () {
    var y = window.scrollY;
    var open = burger && burger.getAttribute('aria-expanded') === 'true';
    if (header) header.classList.toggle('is-solid', y > 60 || open);
    if (cta && !reduce) {
      var r = cta.parentNode.getBoundingClientRect();
      if (r.top < window.innerHeight && r.bottom > 0) {
        var p = (r.top + r.height) / (window.innerHeight + r.height);
        cta.style.transform = 'translate3d(0,' + (-p * 22).toFixed(1) + '%,0)';
      }
    }
    ticking = false;
  };
  window.addEventListener('scroll', function () {
    if (!ticking) { window.requestAnimationFrame(onScroll); ticking = true; }
  }, { passive: true });
  onScroll();

  /* Mobile drawer. */
  if (burger && drawer) {
    burger.addEventListener('click', function () {
      var open = burger.getAttribute('aria-expanded') === 'true';
      burger.setAttribute('aria-expanded', String(!open));
      drawer.hidden = open;
      onScroll();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && burger.getAttribute('aria-expanded') === 'true') {
        burger.setAttribute('aria-expanded', 'false');
        drawer.hidden = true;
        burger.focus();
        onScroll();
      }
    });
  }

  /* Scroll reveal. */
  var reveals = document.querySelectorAll('.ub-reveal');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { entry.target.classList.add('is-in'); io.unobserve(entry.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.1 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('is-in'); });
  }

  /* Drag-to-scroll rails (mouse); touch scrolls natively. */
  document.querySelectorAll('[data-drag]').forEach(function (rail) {
    var down = false, startX = 0, startLeft = 0, moved = false;
    rail.addEventListener('pointerdown', function (e) {
      if (e.pointerType !== 'mouse') return;
      down = true; moved = false; startX = e.clientX; startLeft = rail.scrollLeft;
      rail.classList.add('is-dragging');
    });
    window.addEventListener('pointermove', function (e) {
      if (!down) return;
      var dx = e.clientX - startX;
      if (Math.abs(dx) > 3) moved = true;
      rail.scrollLeft = startLeft - dx;
    });
    window.addEventListener('pointerup', function () { down = false; rail.classList.remove('is-dragging'); });
    rail.addEventListener('click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); } }, true);
  });

  /* Add-to-cart feedback: toast + badge bump (WooCommerce fires this through jQuery). */
  var toast = document.querySelector('[data-toast]');
  var timer;
  var cartUrl = (window.wc_add_to_cart_params && window.wc_add_to_cart_params.cart_url) || '/cart/';
  var onAdded = function (btn) {
    var name = '';
    var card = btn && btn.closest ? btn.closest('.ush-card') : null;
    if (card) { var n = card.querySelector('.ush-card__name'); if (n) name = n.textContent.trim(); }
    if (toast) {
      toast.innerHTML = (name ? name + ' added to your cart.' : 'Added to your cart.') + '<a href="' + cartUrl + '">View cart</a>';
      toast.classList.add('is-on');
      clearTimeout(timer);
      timer = setTimeout(function () { toast.classList.remove('is-on'); }, 3600);
    }
    var badge = document.querySelector('.ush-cart-count');
    if (badge) { badge.classList.remove('is-bump'); void badge.offsetWidth; badge.classList.add('is-bump'); }
  };
  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart', function (e, fragments, hash, $btn) { onAdded($btn && $btn[0]); });
  }
})();
