<?php
/**
 * ミニCMS 設定
 * チャンネルは「お知らせの種類」。キーは半角英小文字で。
 * 増やしたら data/ の下に同じ名前のフォルダを作る（管理画面が自動で作ります）。
 */
return [
    'site_name' => 'こむぎ舎',

    // 管理画面の「サイトを見る」のリンク先
    'site_url' => '/',

    // 日付の判定（予約公開など）に使うタイムゾーン
    'timezone' => 'Asia/Tokyo',

    'channels' => [
        'info'  => 'お知らせ',
        'event' => 'パン教室',
    ],

    // 埋め込みを許可するサイト（別ドメインから読む場合だけ書く）
    // 例：['https://example.com', 'https://glossary.example.org']
    // 空のままなら、どこからでも読める
    'allowed_origins' => [],

    // 1回のAPIで返す最大件数
    'max_items' => 50,
];
