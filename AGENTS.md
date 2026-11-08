# AGENTS.md — AIへの作業ルール

このサイトをAI（Claudeなど）に直してもらうときのルールです。
AIは作業の前にこのファイルと `docs/DESIGN.md`、`docs/CONTENT.md` を読んでください。
しくみの全体は `docs/HOW-IT-WORKS.md`、このプロジェクトの考え方（なぜ小さいままでいるのか）は `docs/FOR-MAKERS.md`、お知らせのしくみの仕様は `docs/FOR-ENGINEERS.md` にあります。

説明書を1枚にまとめた `docs/chibiweb-all.md` もあります（各mdをつなげたもの）。**`docs/` のmdを直したら、`chibiweb-all.md` も作り直すこと。**

## フォルダ

- `public/`：サーバーに上げるもの。サイトの本体はすべてここ
- `docs/`：説明のmd。サーバーには上げない
- `photos/`：写真の元データ。サーバーには上げない

## このサイトのしくみ

- ページは静的HTML。ビルド（変換作業）はありません。ファイルを直せばそのまま反映されます
- ヘッダー・フッターは `public/site.json` と `public/assets/site.js` が作ります。**各ページのHTMLにメニューを直接書かない**
- お知らせは `public/news/data/<チャンネル>/*.md`。ページには `<div data-news>` で差し込まれます

## 触ってよいところ

| やりたいこと | 直すファイル |
|---|---|
| ページの文章を直す | `public/` の各 `.html` の `<main>` の中 |
| 新しいページを作る | `public/about.html` をコピーして作る（下記の手順） |
| メニューを変える | `public/site.json` の `menu` |
| 色・文字を変える | `public/assets/style.css` の `:root` の変数だけ |
| お知らせを書く | `public/news/data/<チャンネル>/` に新しい `.md` |

## 触らないところ（人に確認してから）

- `public/news/` の中の `.php` と `embed.js`、`public/assets/site.js`
- `public/news/data/.auth.php`（パスワード。**絶対に読まない・書かない・表示しない**）
- `.htaccess`
- `style.css` の `:root` 以外（新しい部品が必要なら、先に提案する）

## 新しいページの作り方

1. `public/about.html` をコピーする
2. `<title>` と `<meta name="description">` を書き換える
3. `.page-head` の `h1` とリード文を書き換える（パンくずは置かない。DESIGN.md参照）
4. 本文は `<article class="prose">` の中に書く。使える部品は DESIGN.md の一覧から選ぶ
5. 必要なら `site.json` の `menu` に足す
6. CONTENT.md のページ一覧に1行足す

## 写真の入れ方

- 元の写真は `photos/` に置いてもらう（公開しないフォルダ。サーバーには上げない）
- AIが横幅 800・1600・2400px の WebP に変換し、`public/assets/img/` に置く
- メイン写真は3:2。大事なものが中央の帯に入っているか確かめる
- `alt` には「何が写っているか」を短い文で書く

## 作業のきまり

- 【重要】見本やダミーの文章は、本物を想像して書く。「ここに文章が入ります」は使わない（`docs/DESIGN.md` 参照）
- 直す前に、何をどう直すかを短く伝える
- 1回の作業で直すのは、頼まれた範囲だけ。ついでの「改善」はしない（提案はしてよい）
- 新しいCSSクラスや色を勝手に作らない
- 直したあと、変えたファイルと変えた内容を一覧にして伝える
- 元に戻せるように、大きな変更の前は日付つきのコピーを残すか、Gitに記録する
- 個人情報（電話番号・住所・メール）を新しく書き足すときは、人に確認する

## お知らせを書くとき

```markdown
---
date: YYYY-MM-DD
title: タイトル
link:
status: published
---
本文
```

- ファイル名は `YYYY-MM-DD-xxxxxx.md`（xxxxxx は英小文字と数字6文字）
- 本文で使えるのは：段落、**太字**、[リンク](URL)、「- 」の箇条書きだけ
- 公開してよいか迷うものは `status: draft` にしておく
