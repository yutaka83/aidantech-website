<?php
/**
 * Collect every image referenced by the reference design at
 * nurzamf.github.io/lkim — from the HTML (img/src, srcset, source, meta,
 * favicons), the stylesheet (url(...)) and the page script — and download them
 * into reference/images/.
 *
 * Same-origin files keep their path under the site; third-party files are
 * grouped by host so their provenance stays obvious.
 *
 * Writes reports/reference-images.csv.
 */

require __DIR__ . '/lib.php';

const REF_BASE    = 'https://nurzamf.github.io/lkim/';
const CONCURRENCY = 6;

$dest = BASE . '/reference/images';

/* ── Pull the three source files ─────────────────────────────────────────── */

$sources = [];

foreach (['' => 'index.html', 'styles.css' => 'styles.css', 'script.js' => 'script.js'] as $path => $label) {
    [$body, , $code] = http_get(REF_BASE . $path);

    if ($body === false) {
        out("  ! could not fetch $label (HTTP $code)");
        continue;
    }

    $sources[$label] = $body;
    out(sprintf('  %-12s %s bytes', $label, number_format(strlen($body))));
}

if (!$sources) {
    out('Nothing fetched.');
    exit(1);
}

/* ── Extract candidate URLs ──────────────────────────────────────────────── */

$found = [];

/** Record a URL against the file it came from. */
function note(array &$found, string $url, string $where): void
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($url === '' || str_starts_with($url, 'data:')) {
        return;
    }

    $found[$url][$where] = true;
}

$html = $sources['index.html'] ?? '';

// img src, and every candidate in a srcset
if (preg_match_all('#<img[^>]+>#i', $html, $tags)) {
    foreach ($tags[0] as $tag) {
        if (preg_match('#\ssrc="([^"]+)"#i', $tag, $m)) {
            note($found, $m[1], 'img');
        }

        if (preg_match('#\ssrcset="([^"]+)"#i', $tag, $m)) {
            foreach (explode(',', $m[1]) as $candidate) {
                note($found, trim(explode(' ', trim($candidate))[0]), 'srcset');
            }
        }
    }
}

// <source srcset>, <link rel=icon|apple-touch-icon>, og:image / twitter:image
if (preg_match_all('#<source[^>]+srcset="([^"]+)"#i', $html, $m)) {
    foreach ($m[1] as $set) {
        foreach (explode(',', $set) as $candidate) {
            note($found, trim(explode(' ', trim($candidate))[0]), 'source');
        }
    }
}

if (preg_match_all('#<link[^>]+rel="[^"]*icon[^"]*"[^>]*href="([^"]+)"#i', $html, $m)) {
    foreach ($m[1] as $u) {
        note($found, $u, 'favicon');
    }
}

if (preg_match_all('#<meta[^>]+(?:property|name)="(?:og:image|twitter:image)"[^>]+content="([^"]+)"#i', $html, $m)) {
    foreach ($m[1] as $u) {
        note($found, $u, 'meta');
    }
}

// Inline style="background:url(...)" plus the stylesheet and the script
foreach (['index.html', 'styles.css', 'script.js'] as $label) {
    if (!isset($sources[$label])) {
        continue;
    }

    if (preg_match_all('#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#i', $sources[$label], $m)) {
        foreach ($m[1] as $u) {
            note($found, $u, $label);
        }
    }

    // Bare image paths in markup or script strings
    if (preg_match_all('#[\'"]([^\'"\s]+\.(?:jpe?g|png|gif|webp|svg|avif)(?:\?[^\'"\s]*)?)[\'"]#i', $sources[$label], $m)) {
        foreach ($m[1] as $u) {
            note($found, $u, $label);
        }
    }
}

out('');
out('Distinct image references: ' . count($found));

/* ── Resolve to absolute URLs and pick a local path ──────────────────────── */

function absolutise(string $url): string
{
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }

    return REF_BASE . ltrim($url, '/');
}

$plan  = [];
$taken = [];

foreach (array_keys($found) as $url) {
    $absolute = absolutise($url);
    $parts    = parse_url($absolute);

    if (!isset($parts['host'])) {
        continue;
    }

    $path = $parts['path'] ?? '/';
    $name = basename($path);

    if ($name === '' || !preg_match('#\.(jpe?g|png|gif|webp|svg|avif)$#i', $name)) {
        // Unsplash and friends serve extensionless URLs; name them by host + hash.
        $name = preg_replace('#[^a-z0-9]+#i', '-', trim($path, '/')) ?: 'image';
        $name = substr($name, 0, 60) . '-' . substr(md5($absolute), 0, 8) . '.jpg';
    }

    $isLocal = str_contains($parts['host'], 'nurzamf.github.io');
    $folder  = $isLocal ? 'site' : preg_replace('#[^a-z0-9.]+#i', '-', $parts['host']);
    $local   = $folder . '/' . $name;

    if (isset($taken[strtolower($local)])) {
        $local = $folder . '/' . pathinfo($name, PATHINFO_FILENAME) . '-' . substr(md5($absolute), 0, 6)
            . '.' . (pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg');
    }

    $taken[strtolower($local)] = true;

    $plan[] = [
        'url'    => $absolute,
        'path'   => $local,
        'origin' => $isLocal ? 'reference site' : $parts['host'],
        'where'  => implode(' + ', array_keys($found[$url])),
    ];
}

out('Resolved to ' . count($plan) . ' downloads');
out('');

/* ── Download ────────────────────────────────────────────────────────────── */

$multi   = curl_multi_init();
$running = [];
$index   = 0;
$rows    = [];
$ok      = 0;
$failed  = 0;
$bytes   = 0;

while ($index < count($plan) || $running) {
    while (count($running) < CONCURRENCY && $index < count($plan)) {
        $item = $plan[$index];
        $full = $dest . '/' . $item['path'];

        if (!is_dir(dirname($full))) {
            mkdir(dirname($full), 0775, true);
        }

        $fh = fopen($full . '.part', 'w');
        $ch = curl_init($item['url']);

        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => UA,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FAILONERROR    => true,
        ]);

        curl_multi_add_handle($multi, $ch);
        $running[(int) $ch] = ['ch' => $ch, 'fh' => $fh, 'item' => $item, 'full' => $full];
        $index++;
    }

    do {
        $status = curl_multi_exec($multi, $active);
    } while ($status === CURLM_CALL_MULTI_PERFORM);

    curl_multi_select($multi, 0.4);

    while ($info = curl_multi_info_read($multi)) {
        $key = (int) $info['handle'];
        $job = $running[$key];

        $code = (int) curl_getinfo($job['ch'], CURLINFO_HTTP_CODE);
        $type = (string) curl_getinfo($job['ch'], CURLINFO_CONTENT_TYPE);

        curl_multi_remove_handle($multi, $job['ch']);
        curl_close($job['ch']);
        fclose($job['fh']);
        unset($running[$key]);

        $part  = $job['full'] . '.part';
        $good  = $info['result'] === CURLE_OK && $code >= 200 && $code < 300 && filesize($part) > 0;
        $size  = $good ? filesize($part) : 0;

        if ($good) {
            rename($part, $job['full']);
            $bytes += $size;
            $ok++;
        } else {
            @unlink($part);
            $failed++;
        }

        $rows[] = [
            $job['item']['path'],
            $job['item']['origin'],
            $job['item']['where'],
            $code,
            $size,
            trim(explode(';', $type)[0]),
            $job['item']['url'],
        ];
    }
}

curl_multi_close($multi);

usort($rows, fn($a, $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

csv_write(
    BASE . '/reports/reference-images.csv',
    ['local_path', 'origin', 'referenced_by', 'http', 'bytes', 'content_type', 'source_url'],
    $rows
);

/* ── Summary ─────────────────────────────────────────────────────────────── */

$byOrigin = [];
foreach ($rows as $r) {
    if ($r[3] >= 200 && $r[3] < 300) {
        $byOrigin[$r[1]][0] = ($byOrigin[$r[1]][0] ?? 0) + 1;
        $byOrigin[$r[1]][1] = ($byOrigin[$r[1]][1] ?? 0) + $r[4];
    }
}

out('Downloaded: ' . $ok);
out('Failed:     ' . $failed);
out(sprintf('Total size: %.1f MB', $bytes / 1048576));
out('');

foreach ($byOrigin as $origin => [$n, $size]) {
    out(sprintf('  %-28s %3d files  %6.1f MB', $origin, $n, $size / 1048576));
}

out('');
out('-> reference/images/');
out('-> reports/reference-images.csv');
