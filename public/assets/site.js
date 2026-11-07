/*!
 * chibiweb kit — ヘッダー・フッター部品 v0.1.0-alpha
 *
 *   <site-header><a href="/">サイト名</a></site-header>
 *   <site-footer></site-footer>
 *   <script src="/assets/site.js" defer></script>
 *
 * 中身は site.json に書く（ロゴ・メニュー・コピーライト）。
 * データ（site.json）と見た目（この部品）は分けておく。
 * v0.1 はスマホファースト：画面の広さにかかわらずハンバーガーメニュー。
 */
(function () {
  'use strict';

  var script = document.currentScript;
  var configUrl = (script && script.getAttribute('data-config')) ||
    (script ? new URL('../site.json', script.src).href : '/site.json');

  var configPromise = fetch(configUrl).then(function (r) {
    if (!r.ok) throw new Error(r.status);
    return r.json();
  });

  function el(tag, attrs, text) {
    var e = document.createElement(tag);
    for (var k in attrs || {}) e.setAttribute(k, attrs[k]);
    if (text != null) e.textContent = text;
    return e;
  }

  function isCurrent(href) {
    try {
      var u = new URL(href, location.href);
      if (u.hash) return false;
      var a = u.pathname.replace(/index\.html$/, '');
      var b = location.pathname.replace(/index\.html$/, '');
      return a === b;
    } catch (e) { return false; }
  }

  /* ---------- ヘッダー ---------- */
  class SiteHeader extends HTMLElement {
    connectedCallback() {
      var self = this;
      configPromise.then(function (cfg) { self.build(cfg); }).catch(function () {
        // 読めなくても、HTMLに直接書いたロゴのリンクはそのまま残る
      });
    }

    build(cfg) {
      this.innerHTML = '';
      var bar = el('div', { class: 'sh-bar' });

      var home = el('a', { class: 'sh-logo', href: cfg.home || '/' });
      if (cfg.logo) {
        home.appendChild(el('img', { src: cfg.logo, alt: cfg.name }));
      } else {
        home.textContent = cfg.name;
      }
      bar.appendChild(home);

      var btn = el('button', { class: 'sh-toggle', type: 'button', 'aria-expanded': 'false', 'aria-controls': 'sh-menu' });
      btn.innerHTML = '<span class="sh-icon" aria-hidden="true"><i></i><i></i><i></i></span><span class="sh-label">メニュー</span>';
      bar.appendChild(btn);

      var nav = el('nav', { class: 'sh-menu', id: 'sh-menu', 'aria-label': 'サイト内メニュー', hidden: '' });
      var ul = el('ul');
      (cfg.menu || []).forEach(function (item) {
        var li = el('li');
        var a = el('a', { href: item.href }, item.label);
        if (isCurrent(item.href)) a.setAttribute('aria-current', 'page');
        li.appendChild(a);
        ul.appendChild(li);
      });
      nav.appendChild(ul);

      this.appendChild(bar);
      this.appendChild(nav);

      var open = function (v) {
        btn.setAttribute('aria-expanded', String(v));
        btn.querySelector('.sh-label').textContent = v ? '閉じる' : 'メニュー';
        nav.hidden = !v;
        document.documentElement.classList.toggle('sh-open', v);
      };
      btn.addEventListener('click', function () { open(btn.getAttribute('aria-expanded') !== 'true'); });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !nav.hidden) { open(false); btn.focus(); }
      });
      nav.addEventListener('click', function (e) { if (e.target.closest('a')) open(false); });
    }
  }

  /* ---------- フッター ---------- */
  class SiteFooter extends HTMLElement {
    connectedCallback() {
      var self = this;
      configPromise.then(function (cfg) {
        self.innerHTML = '';
        var p = el('p', { class: 'sf-copy' }, 'Copyright © ' + new Date().getFullYear() + ' ' + (cfg.copyright || cfg.name));
        self.appendChild(p);
      }).catch(function () {});
    }
  }

  customElements.define('site-header', SiteHeader);
  customElements.define('site-footer', SiteFooter);
})();
