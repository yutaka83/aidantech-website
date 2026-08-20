<?php
/**
 * The default media harvest came up ~9% short of X-WP-Total: WordPress pages
 * media by date, and equal timestamps make that ordering unstable across
 * requests. Re-fetch ordered by id ascending and dedupe, which is stable.
 */

require __DIR__ . '/lib.php';

$fields = 'id,slug,link,title,source_url,mime_type,alt_text,date,post,media_details';
$seen   = [];
$rows   = [];
$page   = 1;
$pages  = 1;

do {
    $url = SRC . '/wp-json/wp/v2/media?per_page=100&orderby=id&order=asc&page=' . $page . '&_fields=' . $fields;
    [$body, $headers, $code] = http_get($url);

    if ($body === false) {
        out("  ! page $page failed (HTTP $code)");
        $page++;
        continue;
    }

    if ($page === 1) {
        $pages = (int) ($headers['x-wp-totalpages'] ?? 1);
        out('  expecting ' . ($headers['x-wp-total'] ?? '?') . " items across $pages pages");
    }

    $batch = json_decode($body, true) ?: [];
    foreach ($batch as $row) {
        if (!isset($seen[$row['id']])) {
            $seen[$row['id']] = true;
            $rows[] = $row;
        }
    }

    out(sprintf('  page %2d/%d  +%-3d  total %d', $page, $pages, count($batch), count($rows)));
    $page++;
    usleep(150000);
} while ($page <= $pages);

$n = jsonl_write(BASE . '/data/media.jsonl', $rows);
out("wrote $n unique media rows -> data/media.jsonl");
