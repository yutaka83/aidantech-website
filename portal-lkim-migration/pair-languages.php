<?php
/**
 * Phase 8a: pair every Malay item with its English counterpart.
 *
 * The REST API exposes no translation relationship, but each rendered page
 * carries <link rel="alternate" hreflang="en"> pointing at its counterpart.
 * Fetching the Malay pages and reading that tag gives an exact pairing —
 * far better than the 48 pairs the menu structure alone could infer.
 *
 * Writes data/pairs.json and reports/language-pairs.csv.
 */

require __DIR__ . '/lib.php';

const CONCURRENCY = 6;

$resolved = json_decode(file_get_contents(BASE . '/data/resolved.json'), true);

$bySlugEn = [];
$byLink   = [];

foreach ($resolved['articles'] as $a) {
    if ($a['lang'] === 'en') {
        $bySlugEn[$a['slug']] = $a;
    }
    $byLink[normalise_link($a['link'])] = $a;
}

$queue = array_values(array_filter($resolved['articles'], fn($a) => $a['lang'] === 'ms'));

function normalise_link(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    return rtrim(strtolower(rawurldecode($path)), '/') ?: '/';
}

out('Malay items to probe: ' . count($queue));

/* ── Resume support ──────────────────────────────────────────────────────── */

$cacheFile = BASE . '/data/alternates.jsonl';
$known     = [];

if (is_file($cacheFile)) {
    foreach (jsonl_read($cacheFile) as $row) {
        $known[$row['wp_id']] = $row['en'];
    }
    out('Already probed: ' . count($known));
}

$todo = array_values(array_filter($queue, fn($a) => !isset($known[$a['wp_id']])));

/* ── Fetch ───────────────────────────────────────────────────────────────── */

if ($todo) {
    $cache   = fopen($cacheFile, 'a');
    $multi   = curl_multi_init();
    $running = [];
    $index   = 0;
    $done    = 0;

    while ($index < count($todo) || $running) {
        while (count($running) < CONCURRENCY && $index < count($todo)) {
            $item = $todo[$index];
            $ch   = curl_init($item['link']);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => UA,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_ENCODING       => '',
            ]);

            curl_multi_add_handle($multi, $ch);
            $running[(int) $ch] = $item;
            $index++;
        }

        do {
            $status = curl_multi_exec($multi, $active);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        curl_multi_select($multi, 0.5);

        while ($info = curl_multi_info_read($multi)) {
            $key  = (int) $info['handle'];
            $item = $running[$key];
            $html = curl_multi_getcontent($info['handle']);

            curl_multi_remove_handle($multi, $info['handle']);
            curl_close($info['handle']);
            unset($running[$key]);

            $en = null;
            if (is_string($html) && preg_match('#<link[^>]+rel="alternate"[^>]+hreflang="en"[^>]*>#i', $html, $m)) {
                if (preg_match('#href="([^"]+)"#i', $m[0], $h)) {
                    $en = html_entity_decode($h[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }

            fwrite($cache, json_encode(['wp_id' => $item['wp_id'], 'en' => $en], JSON_UNESCAPED_SLASHES) . "\n");
            $known[$item['wp_id']] = $en;

            if (++$done % 100 === 0) {
                out(sprintf('  %4d/%d probed', $done, count($todo)));
            }
        }
    }

    curl_multi_close($multi);
    fclose($cache);
}

/* ── Resolve alternates to article records ───────────────────────────────── */

$pairs   = [];
$noPair  = 0;
$unknown = [];

foreach ($queue as $a) {
    $altUrl = $known[$a['wp_id']] ?? null;

    if (!$altUrl) {
        $noPair++;
        continue;
    }

    $path = normalise_link($altUrl);

    // The EN home page is not a translation of anything useful.
    if ($path === '/en' || $path === '/') {
        $noPair++;
        continue;
    }

    $target = $byLink[$path] ?? null;

    if ($target === null) {
        // Fall back to the trailing slug.
        $slug   = basename($path);
        $target = $bySlugEn[$slug] ?? null;
    }

    if ($target === null || $target['lang'] !== 'en') {
        $unknown[] = [$a['wp_id'], $a['slug'], $altUrl];
        continue;
    }

    $pairs[] = [
        'ms_wp_id' => $a['wp_id'],
        'ms_slug'  => $a['slug'],
        'ms_type'  => $a['type'],
        'en_wp_id' => $target['wp_id'],
        'en_slug'  => $target['slug'],
        'en_type'  => $target['type'],
    ];
}

file_put_contents(BASE . '/data/pairs.json', json_encode($pairs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

csv_write(
    BASE . '/reports/language-pairs.csv',
    ['ms_wp_id', 'ms_slug', 'ms_type', 'en_wp_id', 'en_slug', 'en_type'],
    array_map('array_values', $pairs)
);

csv_write(BASE . '/reports/language-pairs-unresolved.csv', ['ms_wp_id', 'ms_slug', 'alternate_url'], $unknown);

/* ── Which English items end up with no Malay parent ─────────────────────── */

$pairedEn = array_flip(array_column($pairs, 'en_wp_id'));
$orphanEn = [];

foreach ($resolved['articles'] as $a) {
    if ($a['lang'] === 'en' && !isset($pairedEn[$a['wp_id']])) {
        $orphanEn[] = [$a['wp_id'], $a['type'], $a['slug'], $a['category']];
    }
}

csv_write(BASE . '/reports/english-unpaired.csv', ['wp_id', 'type', 'slug', 'category'], $orphanEn);

out('');
out('Pairs found:              ' . count($pairs));
out('Malay items with no EN:   ' . $noPair);
out('Alternates not harvested: ' . count($unknown));
out('English items unpaired:   ' . count($orphanEn));
