# chibiwebをフォーク・改造するエンジニアへ

お知らせのしくみ（core）と、ヘッダー・フッターの部品（kit）の仕様書です。
直す前に、[FOR-MAKERS.md](FOR-MAKERS.md) の「2つの約束」を読んでください。機能を足すときの判断の物差しは「小さいままでいられるか」です。

対象のバージョン：v0.1.0

---

## 1. 全体の構成

```
public/
├─ news/                ← core（お知らせ）。これだけ別のサイトに置いても動く
│   ├─ config.php       設定（この章の「3. 設定ファイル」）
│   ├─ lib.php          共通の関数
│   ├─ api.php          公開用API（JSONを返す）
│   ├─ embed.js         ページに差し込む部品
│   ├─ admin/index.php  管理画面（1ファイル）
│   └─ data/            お知らせ本体と認証情報
│       ├─ .htaccess    外からの直接アクセスを拒否
│       ├─ .auth.php    パスワードのハッシュ（自動で作られる。Gitには入れない）
│       ├─ <チャンネル>/<ID>.md
│       └─ _trash/      削除したお知らせ
├─ assets/site.js       ← kit。ヘッダー・フッターの部品
└─ site.json            ← kit の設定
```

- 動かすのに必要なもの：PHP 8.1 以上。データベース、Composer、ビルド作業は不要
- 外部ライブラリ：使っていない（PHPもJavaScriptも標準機能だけ）
- 文字コード：UTF-8

## 2. データの形式（お知らせ1件＝1ファイル）

### 置き場所とファイル名

```
public/news/data/<チャンネル>/<ID>.md
```

- **ID**：`YYYY-MM-DD-xxxxxx`（正規表現 `^\d{4}-\d{2}-\d{2}-[a-z0-9]{6}$`）。この形でないファイルは無視される
- 管理画面で作ると、`xxxxxx` には16進数6文字が入る。手で作る場合は英小文字と数字6文字なら何でもよい
- まちがえやすい例：`2026-11-09-urgent01`（8文字）、`2026-11-01-info001`（7文字）、`2026-11-01-A1B2C3`（大文字）は、どれも無視される
- チャンネルのフォルダは、管理画面にログインしたときに自動で作られる（手で作ってもよい）

### 中身

```markdown
---
date: 2026-11-07
title: 季節のパンが入れかわりました
link: /menu.html
status: published
---
本文（Markdownの一部が使える）
```

| 項目 | 必須 | 意味 | 省略したとき |
|---|---|---|---|
| `date` | — | 表示する日付。`YYYY-MM-DD` | IDの先頭10文字 |
| `title` | — | タイトル | `(無題)` |
| `link` | — | タイトルからのリンク先。外部URLやPDFでもよい | リンクなし |
| `status` | — | `published`（公開）／`draft`（下書き） | `published` |

- 先頭の `---` で囲んだ部分は、`キー: 値` の1行ずつ。キーは英小文字と `_` だけ。値の前後の空白と引用符は取り除かれる
- **公開の条件**：`status` が `published`、かつ `date` が今日以前（未来の日付は予約公開として隠れる）。「今日」は `config.php` の `timezone` で決まる
- **並び順**：`date` ＋ ID の降順（新しい順）

### 本文で使えるMarkdown（ごく一部）

| 書き方 | 結果 |
|---|---|
| 空行 | 段落の区切り |
| 段落内の改行 | `<br>` |
| `**太字**` | `<strong>` |
| `[文字](URL)` | リンク。URLは `http://` `https://` `/` `./` `../` `#` `mailto:` で始まるものだけ。それ以外（`javascript:` など）はリンクにせず文字だけ残す |
| 行頭の `- ` または `* ` | 箇条書き（ブロックの全行がそうなっているとき） |

本文はすべてHTMLエスケープしてから変換している。HTMLをそのまま書いても、タグとしては出ない。
ほかの書き方（見出し、画像、表など）は対応していない。足すなら、[1章の約束](FOR-MAKERS.md)と相談してから。

## 3. 設定ファイル（`public/news/config.php`）

PHPの配列を返すだけのファイル。

| キー | 型 | 意味 |
|---|---|---|
| `site_name` | 文字列 | 管理画面に出すサイト名 |
| `setup_key` | 文字列 | **最初のパスワードを決めるときの合言葉**。空のままだと、管理画面でパスワードを決められない。決めたあとは使わない |
| `site_url` | 文字列 | 管理画面の「サイトを見る」のリンク先 |
| `timezone` | 文字列 | 予約公開の判定に使うタイムゾーン（例：`Asia/Tokyo`） |
| `channels` | 配列 | `キー => 表示名`。キーは英小文字。最初のものが既定のチャンネルになる |
| `allowed_origins` | 配列 | APIを読んでよい別ドメイン（例：`['https://example.com']`）。空なら、どこからでも読める |
| `max_items` | 整数 | APIが一度に返す最大件数 |

## 4. 公開API（`public/news/api.php`）

読み取り専用。認証なし。

### リクエスト

```
GET api.php?channel=info&count=3
GET api.php?channel=info&count=all
GET api.php?channel=info&id=2026-11-07-a1b2c3
```

| パラメータ | 意味 | 既定 |
|---|---|---|
| `channel` | チャンネルのキー。`config.php` にないものは、最初のチャンネルとして扱う | 最初のチャンネル |
| `count` | 件数。`all` なら `max_items` まで。1〜`max_items` に丸める | `5` |
| `id` | 1件だけ返す（公開中のものだけ） | — |

### レスポンス

```json
{
  "channel": "info",
  "label": "お知らせ",
  "items": [
    {
      "id": "2026-11-07-a1b2c3",
      "date": "2026-11-07",
      "title": "季節のパンが入れかわりました",
      "link": "/menu.html",
      "html": "<p>本文をHTMLに変換したもの</p>"
    }
  ]
}
```

- 下書きと予約公開中のものは含まれない
- ヘッダー：`Content-Type: application/json; charset=utf-8`、`Cache-Control: public, max-age=60`
- CORS：`allowed_origins` が空なら `Access-Control-Allow-Origin: *`。指定があれば、一致したOriginだけを返す

**この形（データ形式とAPIのレスポンス）が、chibiwebの約束です。** PHP以外で作り直すときも、ここを守れば `embed.js` とページはそのまま使えます。

## 5. 埋め込み部品（`public/news/embed.js`）

```html
<div data-news data-channel="info" data-count="3"></div>
<script src="/news/embed.js" defer></script>
```

- `data-news` のついた要素を、ページ読み込み時にすべて探して、それぞれAPIを呼ぶ
- APIのURLは、`embed.js` 自身のURLから決める（同じフォルダの `api.php`）。別ドメインからでも使える

| 属性 | 値 | 既定 |
|---|---|---|
| `data-channel` | チャンネルのキー | 最初のチャンネル |
| `data-count` | 件数／`all` | `5` |
| `data-style` | `compact`（日付＋タイトル）／`full`（本文も）／`alert`（緊急用。本文つきの箱） | `compact` |
| `data-link` | タイトルのリンク先。`{id}` がIDに置き換わる。お知らせ側に `link` があればそちらが優先 | なし |
| `data-more` | 「〜の一覧へ」のリンク先（`compact` のときだけ） | なし |
| `data-empty` | `hide` なら、0件や読み込み失敗のとき要素ごと隠す | 「まだありません」と表示 |

### 出力するHTMLとクラス名

```html
<div class="mini-news mini-news--compact">
  <ul class="mini-news__list">
    <li class="mini-news__item" id="2026-11-07-a1b2c3">
      <time class="mini-news__date" datetime="2026-11-07">2026.11.07</time>
      <span class="mini-news__title"><a href="…">タイトル</a></span>
      <!-- full / alert のときだけ -->
      <div class="mini-news__body">…</div>
      <a class="mini-news__link">詳しく見る</a>
    </li>
  </ul>
  <a class="mini-news__more">お知らせの一覧へ</a>
</div>
```

- 見た目は `style.css` の「8. お知らせ部品」で決める。クラス名は変えないこと（テーマとの約束）
- 各項目の `id` がお知らせのIDなので、`/news.html#ID` でページ内の位置に飛べる
- 本文のHTMLはサーバー側でエスケープ済みのものを、そのまま差し込んでいる

## 6. 管理画面（`public/news/admin/index.php`）

1ファイルで、ログイン、一覧、作成・編集、削除をすべて扱う。

### 認証

- ユーザーは1人だけ。パスワードのハッシュ（`password_hash` / `PASSWORD_DEFAULT`）を `data/.auth.php` に保存
- `.auth.php` がないときは「はじめに」の画面になる。`config.php` の `setup_key` と一致する合言葉を入れた人だけがパスワードを決められる。`setup_key` が空なら、決められない
- パスワードは10文字以上
- ログインに失敗したとき、合言葉をまちがえたときは、2秒待たせる（簡易的な総当たり対策）
- セッション名は `chibiweb`。ログイン成功時にセッションIDを作り直す。Cookieは `HttpOnly`、`SameSite=Strict`、HTTPSなら `Secure`
- パスワード管理アプリが保存しやすいように、画面には出さないユーザー名欄（値は `admin`）を送っている

### 操作

| 操作 | 方法 | 内容 |
|---|---|---|
| 一覧 | GET `?channel=` | 下書き・予約中も含めて新しい順 |
| 新規 | GET `?action=new` | 空のフォーム |
| 編集 | GET `?action=edit&id=` | |
| 保存 | POST `action=save` | IDがなければ `日付-16進6文字` で新しく作る。ファイルは排他ロックで書く |
| 削除 | POST `action=delete` | `data/_trash/<チャンネル>--<ID>.md` に移す（消さない） |
| ログアウト | POST `action=logout` | |

- POSTはすべて、セッションに持たせたCSRFトークンを確かめる
- 画面の出力は `htmlspecialchars` でエスケープしている

## 7. ヘッダー・フッター（kit：`public/assets/site.js`）

```html
<site-header><a href="/">サイト名</a></site-header>
<site-footer></site-footer>
<script src="/assets/site.js" defer></script>
```

- カスタム要素 `site-header` と `site-footer` を定義している
- 設定は `site.json`。場所は `site.js` から見て `../site.json`。`<script data-config="…">` で変えられる
- `site.json` が読めないときは、HTMLに直接書いた中身（ロゴのリンクなど）がそのまま残る

```json
{
  "name": "こむぎ舎",
  "logo": "",
  "home": "/",
  "menu": [ { "label": "ホーム", "href": "/" } ],
  "copyright": "Komugisha"
}
```

- `logo` が空ならサイト名を文字で出す
- メニューは、いま開いているページに `aria-current="page"` をつける（`#` つきのリンクは対象外）
- フッターは `Copyright © 年 copyright` を出す。年は自動

## 8. セキュリティについて知っておくこと

- `data/` は `.htaccess` で外から読めないようにしている。nginxなどでは、同じことを設定で行うこと（README参照）
- `.auth.php` は `.php` なので、万一アクセスされても中身は表示されない
- 総当たり対策は簡易的。本格的にするなら、ログイン試行の回数を記録する、管理画面のフォルダ名を変える、サーバー側でIP制限をかける、など
- APIは公開情報しか返さないので、認証をつけていない

## 9. フォーク・移植するときに

- **守ってほしいもの**：2章のデータ形式と、4章のAPIの形。ここが同じなら、既存のサイトのお知らせとページがそのまま使える
- **自由に替えてよいもの**：管理画面の作り、保存の方法、動かす言語
- 例：GitHubのファイルをそのまま `data/` として使い、ビルドでJSONを書き出す「サーバーなし版」も、この形を守れば `embed.js` と組み合わせられる
- 変えたら、`CHANGELOG.md` に書き残してください。説明書（`docs/`）も一緒に直すと、次の人が助かります
