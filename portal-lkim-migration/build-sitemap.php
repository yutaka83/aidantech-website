<?php
/**
 * Phase 9d: the two sitemaps a government portal needs.
 *
 *   1. sitemap.xml   for search engines, with hreflang alternates, plus a
 *                    Sitemap: line in robots.txt
 *   2. Peta Laman    the human-readable page SPLaSK looks for, generated from
 *                    the live menu tree and written into the article the footer
 *                    already points at (the imported one held a WordPress
 *                    [pagelist] shortcode that never rendered)
 *
 * Both are derived from the same route table relink.php uses, so all three
 * stay consistent. Re-run after content or menu changes.
 *
 * Usage:
 *   php build-sitemap.php
 *   php build-sitemap.php --base=https://www.lkim.gov.my
 *   php build-sitemap.php --dry-run
 */

const _JEXEC = 1;

define('JPATH_BASE', 'C:\\Users\\hiday\\Herd\\portal-lkim');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

require __DIR__ . '/lib.php';

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;

/* ── Boot ────────────────────────────────────────────────────────────────── */

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

$app = $container->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();
$app->getLanguage()->load('joomla', JPATH_ADMINISTRATOR);

$db = $container->get(DatabaseInterface::class);

$adminId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select('u.id')->from($db->quoteName('#__users', 'u'))
        ->join('INNER', $db->quoteName('#__user_usergroup_map', 'm'), 'm.user_id = u.id')
        ->where('m.group_id = 8')->order('u.id ASC'),
    0,
    1
)->loadResult();

$app->loadIdentity(new User($adminId));

if (!defined('JPATH_COMPONENT')) {
    define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_content');
    define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_content');
    define('JPATH_COMPONENT_SITE', JPATH_SITE . '/components/com_content');
}

$opts   = getopt('', ['base::', 'dry-run']);
$dryRun = isset($opts['dry-run']);

$config = new JConfig();
$base   = rtrim($opts['base'] ?? ($config->live_site ?: 'https://portal-lkim.test'), '/');

out('Base URL: ' . $base);

/* ── Routes ──────────────────────────────────────────────────────────────── */

$menuItems = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName(['id', 'parent_id', 'title', 'path', 'link', 'type', 'menutype', 'level', 'lft', 'home']))
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('published') . ' = 1')
        ->where($db->quoteName('id') . ' > 1')
        ->order($db->quoteName('lft') . ' ASC')
)->loadAssocList('id');

$articleRoute  = [];
$categoryRoute = [];

foreach ($menuItems as $item) {
    if ($item['type'] !== 'component') {
        continue;
    }

    if (preg_match('#view=article&id=(\d+)#', $item['link'], $m)) {
        $articleRoute[(int) $m[1]] = '/' . $item['path'];
    } elseif (preg_match('#view=category.*?[&?]id=(\d+)#', $item['link'], $m)) {
        $categoryRoute[(int) $m[1]] = '/' . $item['path'];
    }
}

$articles = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName(['id', 'title', 'alias', 'catid', 'language', 'created', 'modified', 'metadata']))
        ->from($db->quoteName('#__content'))
        ->where($db->quoteName('state') . ' = 1')
        ->where($db->quoteName('access') . ' = 1')
)->loadAssocList('id');

/** @return string|null site-relative path */
function route_for(array $article, array $articleRoute, array $categoryRoute): ?string
{
    $id = (int) $article['id'];

    if (isset($articleRoute[$id])) {
        $path = $articleRoute[$id];
    } elseif (isset($categoryRoute[(int) $article['catid']])) {
        $path = $categoryRoute[(int) $article['catid']] . '/' . $article['alias'];
    } else {
        return null;
    }

    return $article['language'] === 'en-GB' ? '/en' . $path : $path;
}

/* ── Which articles carry an English translation ────────────────────────── */

$enLanguageId = (int) $db->setQuery(
    $db->getQuery(true)->select($db->quoteName('lang_id'))->from($db->quoteName('#__languages'))
        ->where($db->quoteName('lang_code') . ' = ' . $db->quote('en-GB'))
)->loadResult();

$translated = array_flip(
    $db->setQuery(
        $db->getQuery(true)->select('DISTINCT ' . $db->quoteName('reference_id'))
            ->from($db->quoteName('#__falang_content'))
            ->where($db->quoteName('language_id') . ' = ' . $enLanguageId)
            ->where($db->quoteName('reference_table') . ' = ' . $db->quote('content'))
            ->where($db->quoteName('published') . ' = 1')
    )->loadColumn()
);

/* ── 1. sitemap.xml ──────────────────────────────────────────────────────── */

out('');
out('sitemap.xml');

/**
 * How often a section changes, and how much it matters, keyed by the top of
 * its category path. Archive material is stable and low priority; news moves.
 */
function cadence(string $categoryPath): array
{
    return match (true) {
        str_starts_with($categoryPath, 'berita')  => ['weekly', '0.7'],
        str_starts_with($categoryPath, 'arkib')   => ['yearly', '0.3'],
        str_starts_with($categoryPath, 'lain')    => ['monthly', '0.4'],
        default                                   => ['monthly', '0.8'],
    };
}

$categoryPaths = [];
foreach ($db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'path']))->from($db->quoteName('#__categories'))
        ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
)->loadAssocList() as $row) {
    $categoryPaths[(int) $row['id']] = $row['path'];
}

$urls = [];

// Home, in both languages.
$urls[] = ['loc' => '/', 'lastmod' => date('Y-m-d'), 'freq' => 'daily', 'pri' => '1.0', 'alt' => ['ms' => '/', 'en' => '/en']];

// Category landing pages, from the menus that route them.
foreach ($categoryRoute as $catId => $path) {
    [$freq, $pri] = cadence($categoryPaths[$catId] ?? '');

    $urls[] = [
        'loc'     => $path,
        'lastmod' => date('Y-m-d'),
        'freq'    => $freq,
        'pri'     => $pri,
        'alt'     => ['ms' => $path, 'en' => '/en' . $path],
    ];
}

// Articles.
$skipped = 0;

foreach ($articles as $article) {
    $path = route_for($article, $articleRoute, $categoryRoute);

    if ($path === null) {
        $skipped++;
        continue;
    }

    [$freq, $pri] = cadence($categoryPaths[(int) $article['catid']] ?? '');

    $stamp = $article['modified'] && !str_starts_with($article['modified'], '0000')
        ? $article['modified']
        : $article['created'];

    $entry = [
        'loc'     => $path,
        'lastmod' => date('Y-m-d', strtotime($stamp)),
        'freq'    => $freq,
        'pri'     => $pri,
        'alt'     => [],
    ];

    // Only advertise an alternate where one genuinely exists.
    if ($article['language'] !== 'en-GB' && isset($translated[(int) $article['id']])) {
        $entry['alt'] = ['ms' => $path, 'en' => '/en' . $path];
    }

    $urls[] = $entry;
}

$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
$xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

foreach ($urls as $u) {
    $xml .= "  <url>\n";
    $xml .= '    <loc>' . htmlspecialchars($base . $u['loc'], ENT_XML1) . "</loc>\n";
    $xml .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
    $xml .= '    <changefreq>' . $u['freq'] . "</changefreq>\n";
    $xml .= '    <priority>' . $u['pri'] . "</priority>\n";

    foreach ($u['alt'] as $lang => $altPath) {
        $xml .= '    <xhtml:link rel="alternate" hreflang="' . $lang . '" href="'
            . htmlspecialchars($base . $altPath, ENT_XML1) . '"/>' . "\n";
    }

    $xml .= "  </url>\n";
}

$xml .= "</urlset>\n";

if (!$dryRun) {
    file_put_contents(JPATH_BASE . '/sitemap.xml', $xml);
}

out('  ' . count($urls) . ' URLs (' . $skipped . ' articles had no route)');
out('  ' . number_format(strlen($xml)) . ' bytes -> sitemap.xml');

/* ── robots.txt ──────────────────────────────────────────────────────────── */

$robotsFile = JPATH_BASE . '/robots.txt';
$robots     = is_file($robotsFile) ? file_get_contents($robotsFile) : "User-agent: *\n";

if (!str_contains($robots, 'Sitemap:')) {
    $robots = rtrim($robots) . "\n\nSitemap: " . $base . "/sitemap.xml\n";

    if (!$dryRun) {
        file_put_contents($robotsFile, $robots);
    }

    out('  Sitemap: line added to robots.txt');
} else {
    // Keep an existing line pointing at the current base.
    $updated = preg_replace('#^Sitemap:.*$#m', 'Sitemap: ' . $base . '/sitemap.xml', $robots);

    if ($updated !== $robots && !$dryRun) {
        file_put_contents($robotsFile, $updated);
        out('  Sitemap: line in robots.txt updated');
    } else {
        out('  robots.txt already current');
    }
}

/* ── 2. Peta Laman, the human sitemap ───────────────────────────────────── */

out('');
out('Peta Laman');

// English menu titles come from Falang, so the translated page reads as English.
$menuTitleEn = [];
foreach ($db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['reference_id', 'value']))
        ->from($db->quoteName('#__falang_content'))
        ->where($db->quoteName('language_id') . ' = ' . $enLanguageId)
        ->where($db->quoteName('reference_table') . ' = ' . $db->quote('menu'))
        ->where($db->quoteName('reference_field') . ' = ' . $db->quote('title'))
)->loadAssocList() as $row) {
    $menuTitleEn[(int) $row['reference_id']] = $row['value'];
}

$children = [];
foreach ($menuItems as $item) {
    $children[(int) $item['parent_id']][] = $item;
}

/**
 * Render one menu as a nested list. Headings stay as plain text, because that
 * is what they are on the site too.
 */
function render_branch(array $children, int $parentId, string $menutype, array $menuTitleEn, bool $english, int $depth = 0): string
{
    $items = array_filter(
        $children[$parentId] ?? [],
        fn($i) => $i['menutype'] === $menutype && !$i['home']
    );

    if (!$items) {
        return '';
    }

    $pad  = str_repeat('  ', $depth + 1);
    $html = $pad . "<ul>\n";

    foreach ($items as $item) {
        $id    = (int) $item['id'];
        $title = $english && isset($menuTitleEn[$id]) ? $menuTitleEn[$id] : $item['title'];
        $label = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        $html .= $pad . '  <li>';

        if ($item['type'] === 'heading' || $item['type'] === 'separator') {
            $html .= '<span>' . $label . '</span>';
        } else {
            $href = ($english ? '/en/' : '/') . $item['path'];
            $html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
        }

        $sub = render_branch($children, $id, $menutype, $menuTitleEn, $english, $depth + 2);

        if ($sub !== '') {
            $html .= "\n" . $sub . $pad . '  ';
        }

        $html .= "</li>\n";
    }

    return $html . $pad . "</ul>\n";
}

function peta_laman_body(array $children, array $menuTitleEn, bool $english): string
{
    $t = $english
        ? ['intro' => 'Every section of this portal, in one place.',
           'main'  => 'Main Navigation', 'listing' => 'Listings & Archive', 'policy' => 'Policies & Help']
        : ['intro' => 'Keseluruhan struktur portal ini dalam satu halaman.',
           'main'  => 'Navigasi Utama', 'listing' => 'Senarai & Arkib', 'policy' => 'Dasar & Bantuan'];

    $html  = '<div class="lkim-sitemap">' . "\n";
    $html .= '<p>' . $t['intro'] . "</p>\n";

    foreach ([['mainmenu', $t['main']], ['hiddenmenu', $t['listing']], ['footermenu', $t['policy']]] as [$menutype, $heading]) {
        $branch = render_branch($children, 1, $menutype, $menuTitleEn, $english);

        if ($branch === '') {
            continue;
        }

        $html .= '<h2>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . "</h2>\n" . $branch;
    }

    return $html . "</div>\n";
}

$bodyMs = peta_laman_body($children, $menuTitleEn, false);
$bodyEn = peta_laman_body($children, $menuTitleEn, true);

$countLinks = substr_count($bodyMs, '<a href');
out('  ' . $countLinks . ' links in the generated tree');

// The footer's Peta Laman item points at this article.
$target = $db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'title', 'alias', 'catid', 'state', 'language', 'introtext']))
        ->from($db->quoteName('#__content'))
        ->where($db->quoteName('alias') . ' = ' . $db->quote('peta-laman'))
)->loadAssoc();

if (!$target) {
    out('  ! no article with alias "peta-laman" — nothing to update');
    exit(1);
}

if ($dryRun) {
    out('  would update article #' . $target['id'] . ' (dry run)');
    out('');
    out(substr($bodyMs, 0, 600));
    exit(0);
}

$articleFactory = $app->bootComponent('com_content')->getMVCFactory();
$model          = $articleFactory->createModel('Article', 'Administrator', ['ignore_request' => true]);

// ArticleModel reads catid, title and alias even on an update, so the record's
// identity has to travel with the change or it warns and works by luck.
$saved = $model->save([
    'id'        => (int) $target['id'],
    'title'     => $target['title'],
    'alias'     => $target['alias'],
    'catid'     => (int) $target['catid'],
    'state'     => (int) $target['state'],
    'language'  => $target['language'],
    'introtext' => $bodyMs,
    'fulltext'  => '',
    'metadesc'  => 'Peta laman Portal Rasmi Lembaga Kemajuan Ikan Malaysia.',
]);

if (!$saved) {
    out('  ! ' . $model->getError());
    exit(1);
}

out('  article #' . $target['id'] . ' updated (Bahasa Melayu)');

// And the English translation, replacing whatever was there for these fields.
foreach (['introtext' => $bodyEn, 'fulltext' => '', 'title' => 'Sitemap'] as $field => $value) {
    $db->setQuery(
        $db->getQuery(true)->delete($db->quoteName('#__falang_content'))
            ->where($db->quoteName('language_id') . ' = ' . $enLanguageId)
            ->where($db->quoteName('reference_table') . ' = ' . $db->quote('content'))
            ->where($db->quoteName('reference_id') . ' = ' . (int) $target['id'])
            ->where($db->quoteName('reference_field') . ' = ' . $db->quote($field))
    )->execute();

    $db->setQuery(
        $db->getQuery(true)->insert($db->quoteName('#__falang_content'))
            ->columns($db->quoteName([
                'language_id', 'reference_id', 'reference_table', 'reference_field',
                'value', 'original_value', 'original_text', 'modified', 'modified_by', 'published',
            ]))
            ->values(implode(',', [
                $enLanguageId,
                (int) $target['id'],
                $db->quote('content'),
                $db->quote($field),
                $db->quote($value),
                $db->quote(md5($field === 'introtext' ? $bodyMs : ($target[$field] ?? ''))),
                $db->quote(''),
                $db->quote(date('Y-m-d H:i:s')),
                0,
                1,
            ]))
    )->execute();
}

out('  English translation written');
out('');
out('Done.');
