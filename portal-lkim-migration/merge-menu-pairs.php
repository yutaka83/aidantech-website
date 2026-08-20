<?php
/**
 * The source site only declares 74 hreflang translation pairs, but its Malay
 * and English mega-menus mirror each other item for item. That structural
 * correspondence is a reliable second signal for the static pages, so merge
 * the positional pairs from build-map.php into data/pairs.json where they do
 * not contradict an hreflang pair.
 */

require __DIR__ . '/lib.php';

$resolved = json_decode(file_get_contents(BASE . '/data/resolved.json'), true);
$pairs    = json_decode(file_get_contents(BASE . '/data/pairs.json'), true);

$byId = [];
foreach ($resolved['articles'] as $a) {
    $byId[$a['wp_id']] = $a;
}

$haveMs = array_flip(array_column($pairs, 'ms_wp_id'));
$haveEn = array_flip(array_column($pairs, 'en_wp_id'));

$added   = 0;
$skipped = 0;

foreach ($resolved['pairs'] as $p) {
    if (isset($haveMs[$p['ms']]) || isset($haveEn[$p['en']])) {
        $skipped++;
        continue;
    }

    if (!isset($byId[$p['ms']], $byId[$p['en']])) {
        $skipped++;
        continue;
    }

    $pairs[] = [
        'ms_wp_id' => $p['ms'],
        'ms_slug'  => $p['ms_slug'],
        'ms_type'  => $byId[$p['ms']]['type'],
        'en_wp_id' => $p['en'],
        'en_slug'  => $p['en_slug'],
        'en_type'  => $byId[$p['en']]['type'],
    ];

    $haveMs[$p['ms']] = true;
    $haveEn[$p['en']] = true;
    $added++;
}

file_put_contents(BASE . '/data/pairs.json', json_encode($pairs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

csv_write(
    BASE . '/reports/language-pairs.csv',
    ['ms_wp_id', 'ms_slug', 'ms_type', 'en_wp_id', 'en_slug', 'en_type'],
    array_map('array_values', $pairs)
);

$pairedEn = array_flip(array_column($pairs, 'en_wp_id'));
$orphans  = [];

foreach ($resolved['articles'] as $a) {
    if ($a['lang'] === 'en' && !isset($pairedEn[$a['wp_id']])) {
        $orphans[] = [$a['wp_id'], $a['type'], $a['slug'], $a['category'], $a['link']];
    }
}

csv_write(BASE . '/reports/english-unpaired.csv', ['wp_id', 'type', 'slug', 'category', 'link'], $orphans);

out('Menu-position pairs merged: ' . $added);
out('Already covered / skipped:  ' . $skipped);
out('Total pairs:                ' . count($pairs));
out('English still unpaired:     ' . count($orphans));
