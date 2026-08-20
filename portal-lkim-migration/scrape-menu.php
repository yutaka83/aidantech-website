<?php
/**
 * Phase 2b: the WP menus endpoint needs auth, so read the rendered Max Mega Menu
 * out of the homepage HTML instead and write data/menu.json.
 */

require __DIR__ . '/lib.php';

$path = $argv[1] ?? '/';
$outFile = $argv[2] ?? 'menu.json';
[$html, , $code] = http_get(SRC . $path);
if ($html === false) {
    out("Could not fetch homepage (HTTP $code)");
    exit(1);
}

$doc = new DOMDocument();
libxml_use_internal_errors(true);
$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
$xp = new DOMXPath($doc);

$root = $xp->query('//ul[@id="mega-menu-layers-primary"]')->item(0);
if (!$root) {
    out('Mega menu not found in markup');
    exit(1);
}

/**
 * Walk a <ul> of menu items into a nested array.
 */
function walk(DOMElement $ul, DOMXPath $xp): array
{
    $items = [];

    foreach ($xp->query('./li', $ul) as $li) {
        $a = $xp->query('./a', $li)->item(0);
        if (!$a) {
            continue;
        }

        // The label sits in a text node next to any icon markup.
        $label = trim(preg_replace('/\s+/u', ' ', $a->textContent));
        $href  = trim($a->getAttribute('href'));

        $item = [
            'label'    => $label,
            'url'      => $href,
            'slug'     => url_slug($href),
            'children' => [],
        ];

        $sub = $xp->query('./ul[contains(@class,"mega-sub-menu")]', $li)->item(0);
        if ($sub) {
            $item['children'] = walk($sub, $xp);
        }

        $items[] = $item;
    }

    return $items;
}

function url_slug(string $url): string
{
    if ($url === '' || $url === '#') {
        return '';
    }
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $path = trim($path, '/');
    if ($path === '') {
        return 'home';
    }
    $parts = explode('/', $path);
    return end($parts);
}

$menu = walk($root, $xp);

file_put_contents(BASE . '/data/' . $outFile, json_encode($menu, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$count = 0;
$print = function (array $items, int $depth = 0) use (&$print, &$count) {
    foreach ($items as $it) {
        $count++;
        out(str_repeat('  ', $depth) . '- ' . $it['label'] . '  [' . $it['slug'] . ']');
        if ($it['children']) {
            $print($it['children'], $depth + 1);
        }
    }
};
$print($menu);
out('');
out("$count menu items -> data/$outFile");
