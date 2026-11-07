<?php
/**
 * ミニCMS core — 共通の関数
 * お知らせ1件 = data/<チャンネル>/<id>.md（冒頭に日付・タイトルなど）
 */

declare(strict_types=1);

const MINI_VERSION = '0.1.0-alpha';
define('MINI_ROOT', __DIR__);
define('MINI_DATA', __DIR__ . '/data');

function mini_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require MINI_ROOT . '/config.php';
        date_default_timezone_set($cfg['timezone'] ?? 'Asia/Tokyo');
    }
    return $cfg;
}

/** チャンネル名として使ってよい文字だけに絞る */
function mini_channel(?string $ch): string {
    $cfg = mini_config();
    $ch = $ch ?? '';
    if (!array_key_exists($ch, $cfg['channels'])) {
        $ch = array_key_first($cfg['channels']);
    }
    return $ch;
}

function mini_valid_id(string $id): bool {
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}-[a-z0-9]{6}$/', $id);
}

/** mdファイルを読み、冒頭の項目と本文に分ける */
function mini_parse(string $text): array {
    $meta = [];
    $body = $text;
    if (preg_match('/^---\R(.*?)\R---\R?(.*)$/su', $text, $m)) {
        foreach (preg_split('/\R/u', $m[1]) as $line) {
            if (preg_match('/^([a-z_]+):\s*(.*)$/u', $line, $mm)) {
                $meta[$mm[1]] = trim($mm[2], " \"'");
            }
        }
        $body = $m[2];
    }
    return [$meta, trim($body)];
}

function mini_serialize(array $meta, string $body): string {
    $out = "---\n";
    foreach ($meta as $k => $v) {
        $v = str_replace(["\r", "\n"], ' ', (string) $v);
        $out .= $k . ': ' . $v . "\n";
    }
    return $out . "---\n" . trim($body) . "\n";
}

/** チャンネルのお知らせを新しい順に返す（下書きと公開日前は除く） */
function mini_list(string $channel, bool $includeHidden = false): array {
    mini_config();
    $dir = MINI_DATA . '/' . $channel;
    $items = [];
    foreach (glob($dir . '/*.md') ?: [] as $file) {
        $id = basename($file, '.md');
        if (!mini_valid_id($id)) continue;
        [$meta, $body] = mini_parse((string) file_get_contents($file));
        $item = [
            'id'     => $id,
            'date'   => $meta['date'] ?? substr($id, 0, 10),
            'title'  => $meta['title'] ?? '(無題)',
            'link'   => $meta['link'] ?? '',
            'status' => $meta['status'] ?? 'published',
            'body'   => $body,
        ];
        if (!$includeHidden) {
            if ($item['status'] !== 'published') continue;
            if ($item['date'] > date('Y-m-d')) continue; // 予約公開
        }
        $items[] = $item;
    }
    usort($items, fn($a, $b) => strcmp($b['date'] . $b['id'], $a['date'] . $a['id']));
    return $items;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * ごく小さなMarkdown変換。
 * 対応：段落、改行、**太字**、[リンク](URL)、- 箇条書き
 * それ以上が必要になったら、ライブラリへの置き換えを検討する（Issueで相談）
 */
function mini_markdown(string $md): string {
    $md = str_replace("\r\n", "\n", $md);
    $blocks = preg_split("/\n{2,}/u", trim($md));
    $html = '';
    foreach ($blocks as $block) {
        $lines = explode("\n", $block);
        $isList = count(array_filter($lines, fn($l) => preg_match('/^\s*[-*]\s+/u', $l))) === count($lines);
        if ($isList) {
            $html .= '<ul>';
            foreach ($lines as $l) {
                $html .= '<li>' . mini_inline(preg_replace('/^\s*[-*]\s+/u', '', $l)) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>' . implode('<br>', array_map('mini_inline', $lines)) . '</p>';
        }
    }
    return $html;
}

function mini_inline(string $s): string {
    $s = h($s);
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) {
        $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if (!preg_match('#^(https?://|/|\./|\.\./|\#|mailto:)#', $url)) return $m[1];
        return '<a href="' . h($url) . '">' . $m[1] . '</a>';
    }, $s);
    return $s;
}
