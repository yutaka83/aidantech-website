<?php
/**
 * Verify both sitemaps: every URL in sitemap.xml and every link on the Peta
 * Laman page (both languages) must resolve. A sitemap with dead links is worse
 * than none. Writes reports/qa-sitemap.csv.
 */

require __DIR__ . '/lib.php';

const SITE        = 'https://portal-lkim.test';
const CONCURRENCY = 8;

/** Pull every URL out of sitemap.xml plus the links on the two Peta Laman pages. */
function collect(): array
{
    $targets = [];

    [$xml] = http_get(SITE . '/sitemap.xml');
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string((string) $xml);

    if ($doc) {
        foreach ($doc->url as $u) {
            $targets['sitemap.xml'][] = (string) $u->loc;
        }
    }

    foreach (['/pk-peta-laman' => 'peta-laman (ms)', '/en/pk-peta-laman' => 'peta-laman (en)'] as $path => $label) {
        [$html] = http_get(SITE . $path);

        if (preg_match('#<div class="lkim-sitemap".*?</div>#s', (string) $html, $m)
            && preg_match_all('#<a href="([^"]+)"#', $m[0], $links)) {
            foreach (array_unique($links[1]) as $href) {
                $targets[$label][] = SITE . $href;
            }
        }
    }

    return $targets;
}

$targets = collect();

foreach ($targets as $source => $urls) {
    out(sprintf('%-18s %d URLs', $source, count($urls)));
}

$queue = [];
foreach ($targets as $source => $urls) {
    foreach ($urls as $url) {
        $queue[] = [$source, $url];
    }
}

out('');
out('Checking ' . count($queue) . ' URLs');

$multi   = curl_multi_init();
$running = [];
$index   = 0;
$done    = 0;
$bad     = [];

while ($index < count($queue) || $running) {
    while (count($running) < CONCURRENCY && $index < count($queue)) {
        [$source, $url] = $queue[$index];
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        curl_multi_add_handle($multi, $ch);
        $running[(int) $ch] = [$source, $url];
        $index++;
    }

    do {
        $status = curl_multi_exec($multi, $active);
    } while ($status === CURLM_CALL_MULTI_PERFORM);

    curl_multi_select($multi, 0.3);

    while ($info = curl_multi_info_read($multi)) {
        $key            = (int) $info['handle'];
        [$source, $url] = $running[$key];
        $code           = (int) curl_getinfo($info['handle'], CURLINFO_HTTP_CODE);

        curl_multi_remove_handle($multi, $info['handle']);
        curl_close($info['handle']);
        unset($running[$key]);

        if ($code !== 200) {
            $bad[] = [$source, $url, $code];
        }

        if (++$done % 250 === 0) {
            out(sprintf('  %4d/%d  bad: %d', $done, count($queue), count($bad)));
        }
    }
}

curl_multi_close($multi);

csv_write(BASE . '/reports/qa-sitemap.csv', ['source', 'url', 'http'], $bad);

out('');
out('Checked: ' . $done);
out('Broken:  ' . count($bad) . ' (reports/qa-sitemap.csv)');

foreach (array_slice($bad, 0, 10) as $b) {
    out('  ' . $b[2] . '  ' . $b[1]);
}
