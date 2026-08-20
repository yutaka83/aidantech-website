<?php
/**
 * Phase 3c: download every planned file straight into the Joomla images/ tree.
 *
 * Resumable: data/media-manifest.jsonl records what landed, so re-running only
 * fetches what is still missing. Downloads run six at a time via curl_multi;
 * the source is a live government site, so this stays deliberately modest.
 */

require __DIR__ . '/lib.php';

const DOCROOT     = 'C:/Users/hiday/Herd/portal-lkim';
const CONCURRENCY = 6;

$plan = json_decode(file_get_contents(BASE . '/data/media-plan.json'), true);

/* ── Resume from the manifest ────────────────────────────────────────────── */

$done = [];
$manifestPath = BASE . '/data/media-manifest.jsonl';

if (is_file($manifestPath)) {
    foreach (jsonl_read($manifestPath) as $row) {
        if (($row['ok'] ?? false) && is_file(DOCROOT . '/' . $row['path'])) {
            $done[$row['id']] = true;
        }
    }
}

$queue = array_values(array_filter($plan, fn($p) => !isset($done[$p['id']])));

out('Planned:   ' . count($plan));
out('Already:   ' . count($done));
out('To fetch:  ' . count($queue));

if (!$queue) {
    out('Nothing to do.');
    exit(0);
}

$manifest = fopen($manifestPath, 'a');
$failures = [];
$bytes    = 0;
$okCount  = 0;
$index    = 0;

/**
 * Start one transfer, returning the handle plus the row it belongs to.
 */
function make_handle(array $row): array
{
    $full = DOCROOT . '/' . $row['path'];
    $dir  = dirname($full);

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $fh = fopen($full . '.part', 'w');
    $ch = curl_init($row['url']);

    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FAILONERROR    => true,
    ]);

    return ['ch' => $ch, 'fh' => $fh, 'row' => $row, 'full' => $full];
}

$multi   = curl_multi_init();
$running = [];

while ($index < count($queue) || $running) {
    while (count($running) < CONCURRENCY && $index < count($queue)) {
        $job = make_handle($queue[$index]);
        curl_multi_add_handle($multi, $job['ch']);
        $running[(int) $job['ch']] = $job;
        $index++;
    }

    do {
        $status = curl_multi_exec($multi, $active);
    } while ($status === CURLM_CALL_MULTI_PERFORM);

    curl_multi_select($multi, 0.5);

    while ($info = curl_multi_info_read($multi)) {
        $key = (int) $info['handle'];
        $job = $running[$key];

        $code = (int) curl_getinfo($job['ch'], CURLINFO_HTTP_CODE);
        $err  = curl_error($job['ch']);

        curl_multi_remove_handle($multi, $job['ch']);
        curl_close($job['ch']);
        fclose($job['fh']);

        $part = $job['full'] . '.part';
        $ok   = $info['result'] === CURLE_OK && $code >= 200 && $code < 300 && filesize($part) > 0;

        if ($ok) {
            rename($part, $job['full']);
            $size    = filesize($job['full']);
            $bytes  += $size;
            $okCount++;
        } else {
            @unlink($part);
            $size = 0;
            $failures[] = [$job['row']['id'], $job['row']['url'], $code, $err];
        }

        fwrite($manifest, json_encode([
            'id'   => $job['row']['id'],
            'url'  => $job['row']['url'],
            'path' => $job['row']['path'],
            'ok'   => $ok,
            'code' => $code,
            'size' => $size,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        unset($running[$key]);

        $seen = $okCount + count($failures);
        if ($seen % 100 === 0) {
            out(sprintf('  %5d/%d  ok=%d fail=%d  %.1f MB', $seen, count($queue), $okCount, count($failures), $bytes / 1048576));
        }
    }
}

curl_multi_close($multi);
fclose($manifest);

csv_write(BASE . '/reports/media-failures.csv', ['wp_id', 'url', 'http', 'error'], $failures);

out('');
out('Downloaded: ' . $okCount);
out('Failed:     ' . count($failures) . ($failures ? '  (see reports/media-failures.csv)' : ''));
out(sprintf('Total size: %.1f MB', $bytes / 1048576));
