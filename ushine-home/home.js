/* Ushine Home (Redesign) interactions. No dependencies; jQuery is used only if WooCommerce loaded it. */
(function () {
  'use strict';
  var doc = document.documentElement;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  doc.classList.add('ush-js');

  /* Split hero headline into words for a staggered rise. */
  var split = document.querySelector('[data-split]');
  if (split && !reduce) {
    var i = 0;
    var walk = function (node) {
      Array.prototype.slice.call(node.childNodes).forEach(function (child) {
        if (child.nodeType === 3) {
          var frag = document.createDocumentFragment();
          child.textContent.split(/(\s+)/).forEach(function (part) {
            if (!part) return;
            if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
            var outer = document.createElement('span');
            outer.className = 'ush-word';
            var inner = document.createElement('span');
            inner.style.setProperty('--i', i++);
            inner.textContent = part;
            outer.appendChild(inner);
            frag.appendChild(outer);
          });
          node.replaceChild(frag, child);
        } else if (child.nodeType === 1) {
          walk(child);
        }
      });
    };
    split.setAttribute('aria-label', split.textContent.trim());
    walk(split);
  }

  /* Header: solid on scroll, hide on fast scroll down, show on scroll up. */
  var header = document.querySelector('[data-header]');
  var lastY = window.scrollY;
  var ticking = false;
  var onScroll = function () {
    var y = window.scrollY;
    if (header) {
      header.classList.toggle('is-scrolled', y > 24);
      var drawerOpen = burger && burger.getAttribute('aria-expanded') === 'true';
      header.classList.toggle('is-hidden', !drawerOpen && y > 500 && y > lastY + 4);
      if (y < lastY - 4) header.classList.remove('is-hidden');
    }
    lastY = y;
    ticking = false;
  };
  window.addEventListener('scroll', function () {
    if (!ticking) { window.requestAnimationFrame(onScroll); ticking = true; }
  }, { passive: true });

  /* Mobile drawer. */
  var burger = document.querySelector('[data-burger]');
  var drawer = document.querySelector('[data-drawer]');
  if (burger && drawer) {
    burger.addEventListener('click', function () {
      var open = burger.getAttribute('aria-expanded') === 'true';
      burger.setAttribute('aria-expanded', String(!open));
      drawer.hidden = open;
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && burger.getAttribute('aria-expanded') === 'true') {
        burger.setAttribute('aria-expanded', 'false');
        drawer.hidden = true;
        burger.focus();
      }
    });
  }
  onScroll();

  /* Scroll reveal. */
  var reveals = document.querySelectorAll('.ush-reveal');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { entry.target.classList.add('is-in'); io.unobserve(entry.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('is-in'); });
  }

  /* Hero parallax on pointer move (desktop only). */
  var floats = document.querySelectorAll('[data-parallax]');
  var hero = document.querySelector('.ush-hero__frame');
  if (hero && floats.length && !reduce && window.matchMedia('(pointer: fine)').matches) {
    hero.addEventListener('pointermove', function (e) {
      var r = hero.getBoundingClientRect();
      var x = (e.clientX - r.left) / r.width - 0.5;
      var y = (e.clientY - r.top) / r.height - 0.5;
      floats.forEach(function (el) {
        var d = parseFloat(el.getAttribute('data-parallax')) || 10;
        el.style.setProperty('--px', (x * d).toFixed(1) + 'px');
        el.style.setProperty('--py', (y * d).toFixed(1) + 'px');
      });
    });
    hero.addEventListener('pointerleave', function () {
      floats.forEach(function (el) { el.style.setProperty('--px', '0px'); el.style.setProperty('--py', '0px'); });
    });
  }

  /* Product tabs + rail arrows. */
  var tabsRoot = document.querySelector('[data-tabs]');
  if (tabsRoot) {
    var tabs = Array.prototype.slice.call(tabsRoot.querySelectorAll('[data-tab]'));
    var panels = tabsRoot.querySelectorAll('[data-panel]');
    var prev = tabsRoot.querySelector('[data-rail-prev]');
    var next = tabsRoot.querySelector('[data-rail-next]');
    var activePanel = function () { return tabsRoot.querySelector('[data-panel]:not([hidden])'); };
    var syncArrows = function () {
      var p = activePanel();
      if (!p || !prev || !next) return;
      prev.disabled = p.scrollLeft < 8;
      next.disabled = p.scrollLeft + p.clientWidth >= p.scrollWidth - 8;
    };
    var select = function (tab, focus) {
      var key = tab.getAttribute('data-tab');
      tabs.forEach(function (t) {
        var on = t === tab;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
      });
      panels.forEach(function (p) {
        var on = p.getAttribute('data-panel') === key;
        p.hidden = !on;
        if (on && !reduce) {
          Array.prototype.forEach.call(p.children, function (c, idx) { c.style.setProperty('--i', idx); });
          p.classList.remove('is-entering');
          void p.offsetWidth;
          p.classList.add('is-entering');
          p.scrollLeft = 0;
        }
      });
      if (focus) tab.focus();
      syncArrows();
    };
    tabs.forEach(function (tab, idx) {
      tab.addEventListener('click', function () { select(tab, false); });
      tab.addEventListener('keydown', function (e) {
        var n = null;
        if (e.key === 'ArrowRight') n = tabs[(idx + 1) % tabs.length];
        if (e.key === 'ArrowLeft') n = tabs[(idx - 1 + tabs.length) % tabs.length];
        if (n) { e.preventDefault(); select(n, true); }
      });
    });
    var step = function (dir) {
      var p = activePanel();
      if (!p) return;
      var card = p.querySelector('.ush-card');
      var w = card ? card.getBoundingClientRect().width + 16 : 300;
      p.scrollBy({ left: dir * w * 2, behavior: reduce ? 'auto' : 'smooth' });
    };
    if (prev) prev.addEventListener('click', function () { step(-1); });
    if (next) next.addEventListener('click', function () { step(1); });
    panels.forEach(function (p) { p.addEventListener('scroll', syncArrows, { passive: true }); });
    window.addEventListener('resize', syncArrows);
    syncArrows();
  }

  /* Drag-to-scroll rail for results (mouse); touch scrolls natively. */
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
  var toastTimer;
  var showToast = function (html) {
    if (!toast) return;
    toast.innerHTML = html;
    toast.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.classList.remove('is-on'); }, 3600);
  };
  var cartUrl = (window.wc_add_to_cart_params && window.wc_add_to_cart_params.cart_url) || '/cart/';
  var onAdded = function (btn) {
    var name = '';
    var card = btn && btn.closest ? btn.closest('.ush-card') : null;
    if (card) { var n = card.querySelector('.ush-card__name'); if (n) name = n.textContent.trim(); }
    showToast((name ? name + ' added to your cart.' : 'Added to your cart.') + '<a href="' + cartUrl + '">View cart</a>');
    var badge = document.querySelector('.ush-cart-count');
    if (badge) { badge.classList.remove('is-bump'); void badge.offsetWidth; badge.classList.add('is-bump'); }
  };
  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart', function (e, fragments, hash, $btn) {
      onAdded($btn && $btn[0]);
    });
  }
})();
