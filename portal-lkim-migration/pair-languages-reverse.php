<?php
/**
 * Phase 8a (second pass): probe from the English side.
 *
 * Only 73 of 906 Malay pages declare an English alternate, but the linking is
 * one-sided on the source site - several English pages point back at a Malay
 * page that does not point forward. Probing both directions and taking the
 * union recovers those.
 *
 * Merges into data/pairs.json.
 */

require __DIR__ . '/lib.php';

const CONCURRENCY = 6;

$resolved = json_decode(file_get_contents(BASE . '/data/resolved.json'), true);

$byLink = [];
foreach ($resolved['articles'] as $a) {
    $byLink[normalise_link($a['link'])] = $a;
}

function normalise_link(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    return rtrim(strtolower(rawurldecode($path)), '/') ?: '/';
}

$queue = array_values(array_filter($resolved['articles'], fn($a) => $a['lang'] === 'en'));

out('English items to probe: ' . count($queue));

$cacheFile = BASE . '/data/alternates-en.jsonl';
$known     = [];

if (is_file($cacheFile)) {
    foreach (jsonl_read($cacheFile) as $row) {
        $known[$row['wp_id']] = $row['ms'];
    }
}

$todo = array_values(array_filter($queue, fn($a) => !isset($known[$a['wp_id']])));

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

            $ms = null;
            if (is_string($html) && preg_match('#<link[^>]+rel="alternate"[^>]+hreflang="ms"[^>]*>#i', $html, $m)) {
                if (preg_match('#href="([^"]+)"#i', $m[0], $h)) {
                    $ms = html_entity_decode($h[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }

            fwrite($cache, json_encode(['wp_id' => $item['wp_id'], 'ms' => $ms], JSON_UNESCAPED_SLASHES) . "\n");
            $known[$item['wp_id']] = $ms;

            if (++$done % 50 === 0) {
                out(sprintf('  %4d/%d probed', $done, count($todo)));
            }
        }
    }

    curl_multi_close($multi);
    fclose($cache);
}

/* ── Merge with the forward pass ─────────────────────────────────────────── */

$pairs = json_decode(file_get_contents(BASE . '/data/pairs.json'), true);

$haveMs = array_flip(array_column($pairs, 'ms_wp_id'));
$haveEn = array_flip(array_column($pairs, 'en_wp_id'));

$added   = 0;
$dropped = 0;

foreach ($queue as $en) {
    if (isset($haveEn[$en['wp_id']])) {
        continue;
    }

    $altUrl = $known[$en['wp_id']] ?? null;

    if (!$altUrl) {
        continue;
    }

    $path = normalise_link($altUrl);

    if ($path === '/' || !isset($byLink[$path])) {
        continue;
    }

    $ms = $byLink[$path];

    if ($ms['lang'] !== 'ms' || isset($haveMs[$ms['wp_id']])) {
        // The Malay side already has a translation; do not overwrite it.
        $dropped++;
        continue;
    }

    $pairs[] = [
        'ms_wp_id' => $ms['wp_id'],
        'ms_slug'  => $ms['slug'],
        'ms_type'  => $ms['type'],
        'en_wp_id' => $en['wp_id'],
        'en_slug'  => $en['slug'],
        'en_type'  => $en['type'],
    ];

    $haveMs[$ms['wp_id']] = true;
    $haveEn[$en['wp_id']] = true;
    $added++;
}

file_put_contents(BASE . '/data/pairs.json', json_encode($pairs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

csv_write(
    BASE . '/reports/language-pairs.csv',
    ['ms_wp_id', 'ms_slug', 'ms_type', 'en_wp_id', 'en_slug', 'en_type'],
    array_map('array_values', $pairs)
);

$pairedEn = array_flip(array_column($pairs, 'en_wp_id'));
$orphanEn = [];

foreach ($resolved['articles'] as $a) {
    if ($a['lang'] === 'en' && !isset($pairedEn[$a['wp_id']])) {
        $orphanEn[] = [$a['wp_id'], $a['type'], $a['slug'], $a['category'], $a['link']];
    }
}

csv_write(BASE . '/reports/english-unpaired.csv', ['wp_id', 'type', 'slug', 'category', 'link'], $orphanEn);

out('');
out('Pairs added from the English side: ' . $added);
out('Conflicts skipped:                 ' . $dropped);
out('Total pairs:                       ' . count($pairs));
out('English items still unpaired:      ' . count($orphanEn));
