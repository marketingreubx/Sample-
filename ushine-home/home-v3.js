/* Ushine Home v3 interactions. No dependencies; jQuery only if WooCommerce loaded it. */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.documentElement.classList.add('u3-js');

  /* Header turns solid after the top of the hero, or while the menu is open. */
  var header = document.querySelector('[data-header]');
  var burger = document.querySelector('[data-burger]');
  var menu = document.querySelector('[data-menu]');
  var setHeader = function () {
    var open = burger && burger.getAttribute('aria-expanded') === 'true';
    if (header) header.classList.toggle('is-solid', window.scrollY > 40 || open);
  };
  window.addEventListener('scroll', setHeader, { passive: true });
  setHeader();

  if (burger && menu) {
    var toggle = function (open) {
      burger.setAttribute('aria-expanded', String(open));
      menu.hidden = !open;
      document.body.style.overflow = open ? 'hidden' : '';
      setHeader();
    };
    burger.addEventListener('click', function () { toggle(burger.getAttribute('aria-expanded') !== 'true'); });
    menu.addEventListener('click', function (e) { if (e.target.closest('a')) toggle(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { toggle(false); burger.focus(); } });
  }

  /* Reveal on scroll. */
  var reveals = document.querySelectorAll('.u3-reveal');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('is-in'); });
  }

  /* Before/after compare: drag handle + prev/next between customers. */
  var cmp = document.querySelector('[data-cmp]');
  if (cmp) {
    var range = cmp.querySelector('[data-cmp-range]');
    var slides = cmp.querySelectorAll('.u3-cmp__slide');
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
    var prev = document.querySelector('[data-cmp-prev]');
    var next = document.querySelector('[data-cmp-next]');
    if (prev) prev.addEventListener('click', function () { show(current - 1); });
    if (next) next.addEventListener('click', function () { show(current + 1); });
    /* One gentle hint sweep the first time it scrolls into view. */
    if ('IntersectionObserver' in window && !reduce) {
      var hint = new IntersectionObserver(function (en) {
        if (!en[0].isIntersecting) return;
        hint.disconnect();
        cmp.classList.add('is-hint');
        setTimeout(function () { setPos(25); }, 400);
        setTimeout(function () { setPos(72); }, 1300);
        setTimeout(function () { setPos(50); }, 2200);
        setTimeout(function () { cmp.classList.remove('is-hint'); }, 3200);
      }, { threshold: 0.5 });
      hint.observe(cmp);
    }
  }

  /* Before/after wall: drag with mouse, swipe on touch, slow auto-scroll loop. */
  var rail = document.querySelector('[data-drag]');
  if (rail) {
    var track = rail.querySelector('.u3-wall__track');
    if (track && rail.hasAttribute('data-auto') && !reduce) {
      Array.prototype.slice.call(track.children).forEach(function (c) { var k = c.cloneNode(true); k.setAttribute('aria-hidden', 'true'); track.appendChild(k); });
    }
    var down = false, sx = 0, sl = 0, moved = false, paused = false, resumeT;
    var pause = function () { paused = true; clearTimeout(resumeT); resumeT = setTimeout(function () { paused = false; }, 2500); };
    rail.addEventListener('pointerdown', function (e) { pause(); if (e.pointerType !== 'mouse') return; down = true; moved = false; sx = e.clientX; sl = rail.scrollLeft; rail.classList.add('is-dragging'); });
    window.addEventListener('pointermove', function (e) { if (!down) return; var dx = e.clientX - sx; if (Math.abs(dx) > 3) moved = true; rail.scrollLeft = sl - dx; });
    window.addEventListener('pointerup', function () { down = false; rail.classList.remove('is-dragging'); });
    rail.addEventListener('click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); } }, true);
    rail.addEventListener('mouseenter', function () { paused = true; });
    rail.addEventListener('mouseleave', function () { paused = false; });
    rail.addEventListener('touchstart', pause, { passive: true });
    rail.addEventListener('wheel', pause, { passive: true });
    if (track && rail.hasAttribute('data-auto') && !reduce) {
      var acc = 0;
      var tick = function () {
        if (!paused && !down) {
          acc += 0.5;
          if (acc >= 1) { rail.scrollLeft += Math.floor(acc); acc -= Math.floor(acc); }
          var half = track.scrollWidth / 2;
          if (rail.scrollLeft >= half) rail.scrollLeft -= half;
        }
        requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    }
  }

  /* Add-to-cart feedback. */
  var toast = document.querySelector('[data-toast]');
  var tt;
  var cartUrl = (window.wc_add_to_cart_params && window.wc_add_to_cart_params.cart_url) || '/cart/';
  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart', function (e, f, h, $btn) {
      var btn = $btn && $btn[0];
      var card = btn && btn.closest ? btn.closest('.ush-card') : null;
      var name = card && card.querySelector('.ush-card__name') ? card.querySelector('.ush-card__name').textContent.trim() : '';
      if (toast) {
        toast.innerHTML = (name ? name + ' added to your cart.' : 'Added to your cart.') + '<a href="' + cartUrl + '">View cart</a>';
        toast.classList.add('is-on'); clearTimeout(tt); tt = setTimeout(function () { toast.classList.remove('is-on'); }, 3600);
      }
      var badge = document.querySelector('.ush-cart-count');
      if (badge) { badge.classList.remove('is-bump'); void badge.offsetWidth; badge.classList.add('is-bump'); }
    });
  }
})();
