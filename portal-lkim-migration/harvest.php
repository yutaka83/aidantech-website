<?php
/**
 * Phase 2: pull the full content inventory of lkim.gov.my into data/*.jsonl
 * via its public WordPress REST API.
 */

require __DIR__ . '/lib.php';

out('Harvesting ' . SRC);

$jobs = [
    'categories' => ['file' => 'categories.jsonl', 'fields' => ['id', 'slug', 'name', 'parent', 'count', 'description']],
    'tags'       => ['file' => 'tags.jsonl',       'fields' => ['id', 'slug', 'name', 'count']],
    'pages'      => ['file' => 'pages.jsonl',      'fields' => ['id', 'slug', 'link', 'title', 'content', 'excerpt', 'parent', 'menu_order', 'date', 'modified', 'featured_media', 'status']],
    'posts'      => ['file' => 'posts.jsonl',      'fields' => ['id', 'slug', 'link', 'title', 'content', 'excerpt', 'categories', 'tags', 'date', 'modified', 'featured_media', 'status']],
    'media'      => ['file' => 'media.jsonl',      'fields' => ['id', 'slug', 'link', 'title', 'source_url', 'mime_type', 'alt_text', 'date', 'post', 'media_details']],
];

$summary = [];

foreach ($jobs as $type => $job) {
    out("- $type");
    $n = jsonl_write(BASE . '/data/' . $job['file'], wp_collection($type, $job['fields']));
    $summary[$type] = $n;
    out("  wrote $n rows -> data/{$job['file']}");
}

file_put_contents(BASE . '/data/harvest-summary.json', json_encode([
    'source'     => SRC,
    'fetched_at' => date('c'),
    'counts'     => $summary,
], JSON_PRETTY_PRINT));

out('');
foreach ($summary as $type => $n) {
    out(sprintf('%-12s %6d', $type, $n));
}
