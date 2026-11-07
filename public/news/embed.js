/*!
 * ミニCMS 埋め込み部品 v0.1.0-alpha
 *
 * 使い方：
 *   <div data-news data-channel="info" data-count="3" data-style="compact" data-more="/news.html"></div>
 *   <script src="/news/embed.js" defer></script>
 *
 * data-channel : チャンネル名（省略時は最初のチャンネル）
 * data-count   : 件数。"all" で全件
 * data-style   : "compact"（日付＋タイトル）/ "full"（本文も表示）/ "alert"（緊急のお知らせ用。目立つ箱で本文も表示）
 * data-empty   : "hide" にすると、お知らせが0件のとき何も表示しない（緊急のお知らせ用）
 * data-more    : 「一覧へ」リンク先（compactのとき）
 * data-link    : タイトルからのリンク先。"{id}" が記事IDに置き換わる。例："/news.html#{id}"
 */
(function () {
  'use strict';

  var script = document.currentScript;
  var base = script ? script.src.replace(/embed\.js(\?.*)?$/, '') : '/news/';

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  function formatDate(d) {
    var p = d.split('-');
    return p.length === 3 ? p[0] + '.' + p[1] + '.' + p[2] : d;
  }

  function render(box, data) {
    var style = box.getAttribute('data-style') || 'compact';
    var linkTpl = box.getAttribute('data-link') || '';
    var more = box.getAttribute('data-more');
    var showBody = style === 'full' || style === 'alert';
    box.innerHTML = '';
    box.classList.add('mini-news', 'mini-news--' + style);

    if (!data.items.length) {
      if (box.getAttribute('data-empty') === 'hide') { box.hidden = true; return; }
      box.appendChild(el('p', 'mini-news__empty', 'お知らせはまだありません。'));
      return;
    }
    box.hidden = false;

    var list = el('ul', 'mini-news__list');
    data.items.forEach(function (it) {
      var li = el('li', 'mini-news__item');
      li.id = it.id;
      var time = el('time', 'mini-news__date', formatDate(it.date));
      time.setAttribute('datetime', it.date);
      li.appendChild(time);

      var href = it.link || (linkTpl ? linkTpl.replace('{id}', it.id) : '');
      var title = el(style === 'full' ? 'h3' : (style === 'alert' ? 'strong' : 'span'), 'mini-news__title');
      if (href && !showBody) {
        var a = el('a', null, it.title);
        a.href = href;
        title.appendChild(a);
      } else {
        title.textContent = it.title;
      }
      li.appendChild(title);

      if (showBody) {
        var body = el('div', 'mini-news__body');
        body.innerHTML = it.html; // サーバー側でエスケープ済み
        li.appendChild(body);
        if (it.link) {
          var ext = el('a', 'mini-news__link', '詳しく見る');
          ext.href = it.link;
          li.appendChild(ext);
        }
      }
      list.appendChild(li);
    });
    box.appendChild(list);

    if (more && style === 'compact') {
      var m = el('a', 'mini-news__more', data.label + 'の一覧へ');
      m.href = more;
      box.appendChild(m);
    }

    // ページ内リンク（#id）で来たときに位置を合わせる
    if (location.hash) {
      var t = document.getElementById(location.hash.slice(1));
      if (t && box.contains(t)) t.scrollIntoView();
    }
  }

  function load(box) {
    var ch = box.getAttribute('data-channel') || '';
    var count = box.getAttribute('data-count') || '5';
    var url = base + 'api.php?channel=' + encodeURIComponent(ch) + '&count=' + encodeURIComponent(count);
    fetch(url)
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function (data) { render(box, data); })
      .catch(function () {
        if (box.getAttribute('data-empty') === 'hide') { box.hidden = true; return; }
        box.innerHTML = '';
        box.appendChild(el('p', 'mini-news__empty', 'お知らせを読み込めませんでした。'));
      });
  }

  function init() {
    document.querySelectorAll('[data-news]').forEach(load);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
