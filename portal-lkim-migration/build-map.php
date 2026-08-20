<?php
/**
 * Phase 3: resolve every harvested page and post to a Joomla category, a
 * language, and (where one exists) its counterpart in the other language.
 *
 * Reads:  data/*.jsonl, data/menu.json, data/menu-en.json, map.php
 * Writes: data/resolved.json, reports/*.csv
 */

require __DIR__ . '/lib.php';

$map = require __DIR__ . '/map.php';

/* ── Load source content ─────────────────────────────────────────────────── */

$pages = [];
foreach (jsonl_read(BASE . '/data/pages.jsonl') as $p) {
    $pages[$p['id']] = $p;
}

$posts = [];
foreach (jsonl_read(BASE . '/data/posts.jsonl') as $p) {
    $posts[$p['id']] = $p;
}

$bySlug = [];
foreach ($pages as $p) {
    // Slugs repeat across languages; key on language + slug.
    $bySlug[lang_of($p) . '|' . $p['slug']] = $p['id'];
}

$menuBm = json_decode(file_get_contents(BASE . '/data/menu.json'), true);
$menuEn = json_decode(file_get_contents(BASE . '/data/menu-en.json'), true);

function lang_of(array $item): string
{
    $path = parse_url($item['link'] ?? '', PHP_URL_PATH) ?: '';
    return str_starts_with($path, '/en/') ? 'en' : 'ms';
}

function norm(string $s): string
{
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
}

/* ── Walk a menu tree, assigning each linked item a category ─────────────── */

/**
 * @return array<string, array{category:string, path:array, label:string, position:string}>
 *         keyed by slug
 */
function index_menu(array $items, array $map, string $lang): array
{
    $index = [];

    $walk = function (array $items, array $trail, string $branch) use (&$walk, &$index, $map, $lang) {
        foreach ($items as $i => $item) {
            $label = norm($item['label']);
            $depth = count($trail);

            // Work out which category branch this item sits in.
            if ($depth === 0) {
                $branch = $map['menu_roots'][$label] ?? $branch;
            } elseif ($depth === 1 && isset($map['menu_sections'][$label])) {
                $branch = $map['menu_sections'][$label];
            }

            $path = array_merge($trail, [$i]);

            if ($item['slug'] !== '' && $item['slug'] !== 'home') {
                $index[$item['slug']] = [
                    'category' => $branch ?: 'lain-lain',
                    'path'     => $path,
                    'label'    => html_entity_decode($item['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'position' => implode('.', $path),
                    'lang'     => $lang,
                ];
            }

            if ($item['children']) {
                $walk($item['children'], $path, $branch);
            }
        }
    };

    $walk($items, [], '');

    return $index;
}

$menuIndexBm = index_menu($menuBm, $map, 'ms');
$menuIndexEn = index_menu($menuEn, $map, 'en');

/* ── Pair BM and EN by structural position in their menus ────────────────── */

$byPositionEn = [];
foreach ($menuIndexEn as $slug => $info) {
    $byPositionEn[$info['position']] = $slug;
}

$pairs        = [];
$pairedEnSlug = [];

foreach ($menuIndexBm as $slug => $info) {
    if (isset($byPositionEn[$info['position']])) {
        $enSlug = $byPositionEn[$info['position']];
        $pairs[$slug] = $enSlug;
        $pairedEnSlug[$enSlug] = $slug;
    }
}

/* ── Spam / blocklist ────────────────────────────────────────────────────── */

function is_spam(array $item, array $map): bool
{
    if (in_array($item['slug'], $map['blocklist_slugs'], true)) {
        return true;
    }

    $haystack = mb_strtolower($item['slug'] . ' ' . ($item['title']['rendered'] ?? ''));

    foreach ($map['spam_patterns'] as $needle) {
        if (str_contains($haystack, $needle)) {
            return true;
        }
    }

    return false;
}

/* ── Resolve ─────────────────────────────────────────────────────────────── */

$articles = [];
$skipped  = [];

foreach ($pages as $id => $p) {
    if (is_spam($p, $map)) {
        $skipped[] = ['page', $id, $p['slug'], 'spam pattern'];
        continue;
    }

    $lang    = lang_of($p);
    $index   = $lang === 'en' ? $menuIndexEn : $menuIndexBm;
    $inMenu  = $index[$p['slug']] ?? null;

    $articles[] = [
        'wp_id'     => $id,
        'type'      => 'page',
        'lang'      => $lang,
        'slug'      => $p['slug'],
        'title'     => html_entity_decode($p['title']['rendered'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'category'  => $inMenu['category'] ?? 'lain-lain',
        'in_menu'   => $inMenu !== null,
        'menu_pos'  => $inMenu['position'] ?? null,
        'menu_label' => $inMenu['label'] ?? null,
        'link'      => $p['link'],
        'created'   => $p['date'],
        'modified'  => $p['modified'],
        'featured'  => $p['featured_media'] ?? 0,
        'status'    => $p['status'] ?? 'publish',
    ];
}

foreach ($posts as $id => $p) {
    if (is_spam($p, $map)) {
        $skipped[] = ['post', $id, $p['slug'], 'spam pattern'];
        continue;
    }

    $category = 'lain-lain';
    $has      = array_flip($p['categories'] ?? []);

    foreach ($map['wp_category_priority'] as $wpCat) {
        if (isset($has[$wpCat], $map['wp_categories'][$wpCat])) {
            $category = $map['wp_categories'][$wpCat];
            break;
        }
    }

    $articles[] = [
        'wp_id'      => $id,
        'type'       => 'post',
        'lang'       => lang_of($p),
        'slug'       => $p['slug'],
        'title'      => html_entity_decode($p['title']['rendered'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'category'   => $category,
        'in_menu'    => false,
        'menu_pos'   => null,
        'menu_label' => null,
        'link'       => $p['link'],
        'created'    => $p['date'],
        'modified'   => $p['modified'],
        'featured'   => $p['featured_media'] ?? 0,
        'status'     => $p['status'] ?? 'publish',
    ];
}

/* ── Translation pairs, expressed as WordPress ids ───────────────────────── */

$pairRows = [];
foreach ($pairs as $bmSlug => $enSlug) {
    $bmId = $bySlug['ms|' . $bmSlug] ?? null;
    $enId = $bySlug['en|' . $enSlug] ?? null;

    if ($bmId && $enId) {
        $pairRows[] = ['ms' => $bmId, 'en' => $enId, 'ms_slug' => $bmSlug, 'en_slug' => $enSlug];
    }
}

/* ── Write outputs ───────────────────────────────────────────────────────── */

$resolved = [
    'generated'  => date('c'),
    'categories' => $map['categories'],
    'articles'   => $articles,
    'pairs'      => $pairRows,
    'menu_bm'    => $menuIndexBm,
    'menu_en'    => $menuIndexEn,
];

file_put_contents(BASE . '/data/resolved.json', json_encode($resolved, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

csv_write(BASE . '/reports/skipped-spam.csv', ['type', 'wp_id', 'slug', 'reason'], $skipped);

/* ── Summary ─────────────────────────────────────────────────────────────── */

$byCat = [];
$byLang = [];
foreach ($articles as $a) {
    $byCat[$a['category']] = ($byCat[$a['category']] ?? 0) + 1;
    $byLang[$a['lang']]    = ($byLang[$a['lang']] ?? 0) + 1;
}
ksort($byCat);

out('Articles resolved: ' . count($articles) . '   (ms=' . ($byLang['ms'] ?? 0) . ', en=' . ($byLang['en'] ?? 0) . ')');
out('Translation pairs: ' . count($pairRows));
out('Skipped as spam:   ' . count($skipped));
out('');
out('By category:');
foreach ($byCat as $cat => $n) {
    out(sprintf('  %-42s %4d', $cat, $n));
}
