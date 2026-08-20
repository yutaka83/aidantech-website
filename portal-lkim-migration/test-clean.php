<?php
/** Quick smoke test of the cleaner against a named source slug. */
require __DIR__ . '/lib.php';
require __DIR__ . '/Cleaner.php';

$slug = $argv[1] ?? 'latar-belakang';

$mediaMap = [];
foreach (json_decode(file_get_contents(BASE . '/data/media-plan.json'), true) as $m) {
    $key = strtolower(rawurldecode(preg_split('/[?&#]/', parse_url($m['url'], PHP_URL_PATH))[0]));
    $mediaMap[$key] = $m['path'];
}

$cleaner = new Cleaner($mediaMap, []);

foreach (['pages', 'posts'] as $set) {
    foreach (jsonl_read(BASE . "/data/$set.jsonl") as $p) {
        if ($p['slug'] !== $slug) {
            continue;
        }
        $before = $p['content']['rendered'];
        $after  = $cleaner->clean($before);
        out("slug: {$p['slug']}   before " . strlen($before) . " -> after " . strlen($after) . " bytes");
        out(str_repeat('-', 70));
        out($after);
        out(str_repeat('-', 70));
        out('unresolved links: ' . count($cleaner->unresolvedLinks));
        exit;
    }
}
out("slug not found: $slug");
