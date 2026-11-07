<?php
/**
 * 公開用API：お知らせをJSONで返す
 *   api.php?channel=info&count=5
 *   api.php?channel=info&id=2026-11-07-ab12cd （1件だけ）
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';

$cfg = mini_config();

// 別ドメインからの読み込み
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($cfg['allowed_origins'] === []) {
    header('Access-Control-Allow-Origin: *');
} elseif (in_array($origin, $cfg['allowed_origins'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

$channel = mini_channel($_GET['channel'] ?? null);
$items = mini_list($channel);

if (isset($_GET['id'])) {
    $items = array_values(array_filter($items, fn($i) => $i['id'] === $_GET['id']));
} else {
    $count = $_GET['count'] ?? '5';
    $limit = $count === 'all' ? $cfg['max_items'] : max(1, min((int) $count, $cfg['max_items']));
    $items = array_slice($items, 0, $limit);
}

$out = array_map(fn($i) => [
    'id'    => $i['id'],
    'date'  => $i['date'],
    'title' => $i['title'],
    'link'  => $i['link'],
    'html'  => mini_markdown($i['body']),
], $items);

echo json_encode([
    'channel' => $channel,
    'label'   => $cfg['channels'][$channel],
    'items'   => $out,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
