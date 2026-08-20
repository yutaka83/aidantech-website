<?php
/**
 * Phase 3b: decide where every media file should live in the Joomla images/
 * tree before downloading anything.
 *
 * A file is filed under the category of the first article that references it,
 * so images/ mirrors the sitemap. Documents go to images/muat-turun/<category>,
 * and anything nothing links to lands in images/arkib/YYYY/MM.
 *
 * Writes data/media-plan.json: source_url => local relative path.
 */

require __DIR__ . '/lib.php';

$map      = require __DIR__ . '/map.php';
$resolved = json_decode(file_get_contents(BASE . '/data/resolved.json'), true);

/* ── article wp_id => category ───────────────────────────────────────────── */

$articleCategory = [];
foreach ($resolved['articles'] as $a) {
    $articleCategory[$a['type'] . ':' . $a['wp_id']] = $a['category'];
}

/* ── media id / url index ────────────────────────────────────────────────── */

$media = [];
foreach (jsonl_read(BASE . '/data/media.jsonl') as $m) {
    $media[$m['id']] = $m;
}

// Normalised source url => media id, so content references resolve to a record.
$byUrl = [];
foreach ($media as $id => $m) {
    $byUrl[normalise_url($m['source_url'])] = $id;
}

function normalise_url(string $url): string
{
    $url = preg_replace('#^https?://(www\.)?lkim\.gov\.my#i', '', $url);
    $url = preg_split('/[?&]/', $url)[0];
    return strtolower(rawurldecode($url));
}

/* ── Which article references which file ─────────────────────────────────── */

$owner = [];   // media id => category

$scan = function (string $file, string $type) use (&$owner, $byUrl, $media, $articleCategory) {
    foreach (jsonl_read(BASE . '/' . $file) as $row) {
        $category = $articleCategory[$type . ':' . $row['id']] ?? null;
        if ($category === null) {
            continue;   // spam-skipped
        }

        $html = ($row['content']['rendered'] ?? '') . ' ' . ($row['excerpt']['rendered'] ?? '');

        // Many documents are only linked through a Google Docs viewer wrapper
        // (…/gview?url=<real url>&embedded=true), so stop the match at & and ?.
        if (preg_match_all('#https?://(?:www\.)?lkim\.gov\.my/wp-content/uploads/[^\s"\'<>\\\\)&?]+#i', $html, $hits)) {
            foreach ($hits[0] as $hit) {
                // Strip WordPress' generated size suffix back to the original.
                $clean = preg_replace('/-\d{2,4}x\d{2,4}(?=\.[a-z0-9]+$)/i', '', $hit);

                foreach ([$hit, $clean] as $candidate) {
                    $key = normalise_url($candidate);
                    if (isset($byUrl[$key]) && !isset($owner[$byUrl[$key]])) {
                        $owner[$byUrl[$key]] = $category;
                    }
                }
            }
        }

        // Featured image counts as a reference too.
        $featured = $row['featured_media'] ?? 0;
        if ($featured && isset($media[$featured]) && !isset($owner[$featured])) {
            $owner[$featured] = $category;
        }
    }
};

$scan('data/pages.jsonl', 'page');
$scan('data/posts.jsonl', 'post');

// Content scanning only finds files that are actually linked in the body.
// WordPress also records the post a file was uploaded against, which places
// most of the remainder correctly.
foreach ($media as $id => $m) {
    if (isset($owner[$id]) || empty($m['post'])) {
        continue;
    }

    $category = $articleCategory['post:' . $m['post']] ?? $articleCategory['page:' . $m['post']] ?? null;

    if ($category !== null) {
        $owner[$id] = $category;
    }
}

/* ── Build the plan ──────────────────────────────────────────────────────── */

$documentMimes = ['application/pdf', 'application/msword', 'application/zip'];

$plan   = [];
$taken  = [];
$counts = ['owned' => 0, 'orphan' => 0, 'document' => 0, 'image' => 0];

foreach ($media as $id => $m) {
    $url  = $m['source_url'];
    $file = basename(parse_url($url, PHP_URL_PATH) ?? '');

    if ($file === '') {
        continue;
    }

    $isDocument = str_starts_with($m['mime_type'], 'application/')
        || str_starts_with($m['mime_type'], 'text/');

    // Year/month from the upload path keeps time-series files browsable.
    preg_match('#/uploads/(\d{4})/(\d{2})/#', $url, $ym);
    $period = isset($ym[1]) ? $ym[1] . '/' . $ym[2] : 'lain';

    $category = $owner[$id] ?? null;

    if ($category === null) {
        $counts['orphan']++;
        $dir = $isDocument
            ? 'images/muat-turun/arkib/' . $period
            : 'images/arkib/' . $period;
    } else {
        $counts['owned']++;
        // News-type categories stay date-bucketed; the rest mirror the sitemap.
        $timeSeries = str_starts_with($category, 'berita') || str_starts_with($category, 'arkib');
        $leaf       = $timeSeries ? $category . '/' . $period : $category;
        $dir        = $isDocument ? 'images/muat-turun/' . $leaf : 'images/' . $leaf;
    }

    $counts[$isDocument ? 'document' : 'image']++;

    $path = $dir . '/' . $file;

    // Filenames repeat across upload months; disambiguate with the source id.
    if (isset($taken[strtolower($path)])) {
        $ext  = pathinfo($file, PATHINFO_EXTENSION);
        $base = pathinfo($file, PATHINFO_FILENAME);
        $path = $dir . '/' . $base . '-' . $id . ($ext ? '.' . $ext : '');
    }

    $taken[strtolower($path)] = true;

    $plan[] = [
        'id'    => $id,
        'url'   => $url,
        'path'  => $path,
        'mime'  => $m['mime_type'],
        'alt'   => $m['alt_text'] ?? '',
        'title' => html_entity_decode($m['title']['rendered'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    ];
}

file_put_contents(
    BASE . '/data/media-plan.json',
    json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

/* ── Summary ─────────────────────────────────────────────────────────────── */

$byDir = [];
foreach ($plan as $p) {
    $dir = dirname($p['path']);
    // Collapse the YYYY/MM leaf so the summary stays readable.
    $dir = preg_replace('#/\d{4}/\d{2}$#', '/*', $dir);
    $byDir[$dir] = ($byDir[$dir] ?? 0) + 1;
}
ksort($byDir);

out('Planned files:   ' . count($plan));
out('  referenced:    ' . $counts['owned']);
out('  unreferenced:  ' . $counts['orphan']);
out('  images:        ' . $counts['image']);
out('  documents:     ' . $counts['document']);
out('');
out('Destination folders:');
foreach ($byDir as $dir => $n) {
    out(sprintf('  %-52s %5d', $dir, $n));
}
