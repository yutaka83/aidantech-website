<?php
/**
 * Phase 4b: rewrite the internal links left pointing at lkim.gov.my.
 *
 * The first import could not resolve them - the target articles and the menus
 * that give them URLs did not exist yet. Now that both do, walk the stored
 * article bodies and swap each source permalink for its Joomla route.
 *
 * Routes are derived from the menu tree rather than the site router, because
 * booting the full site application from the CLI is more fragile than reading
 * the same data the router reads.
 *
 * Re-runnable: rebuilding the menus changes routes, so run this again after.
 */

const _JEXEC = 1;

define('JPATH_BASE', 'C:\\Users\\hiday\\Herd\\portal-lkim');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

require __DIR__ . '/lib.php';

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

$app = $container->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();

$db = $container->get(DatabaseInterface::class);

$dryRun = in_array('--dry-run', $argv, true);

/* ── Routes from the menu tree ───────────────────────────────────────────── */

$articleRoute  = [];   // joomla article id => path
$categoryRoute = [];   // joomla category id => path

foreach ($db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'path', 'link', 'type']))
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('published') . ' = 1')
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
)->loadAssocList() as $item) {
    if (preg_match('#view=article&id=(\d+)#', $item['link'], $m)) {
        $articleRoute[(int) $m[1]] = '/' . $item['path'];
    } elseif (preg_match('#view=category.*?[&?]id=(\d+)#', $item['link'], $m)) {
        $categoryRoute[(int) $m[1]] = '/' . $item['path'];
    }
}

out('Menu routes: ' . count($articleRoute) . ' article, ' . count($categoryRoute) . ' category');

/* ── Every article's public path ─────────────────────────────────────────── */

// "fulltext" is a MySQL reserved word, so the column list has to be quoted.
$articles = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName(['id', 'alias', 'catid', 'introtext', 'fulltext', 'metadata']))
        ->from($db->quoteName('#__content'))
)->loadAssocList();

$urlByArticle = [];
$urlBySlug    = [];
$noRoute      = 0;

foreach ($articles as $a) {
    $id = (int) $a['id'];

    if (isset($articleRoute[$id])) {
        $url = $articleRoute[$id];
    } elseif (isset($categoryRoute[(int) $a['catid']])) {
        $url = $categoryRoute[(int) $a['catid']] . '/' . $a['alias'];
    } else {
        $noRoute++;
        continue;
    }

    $urlByArticle[$id] = $url;

    $urlBySlug[strtolower($a['alias'])] = $url;

    // Joomla sanitises aliases on save (underscores become hyphens, and so on),
    // so the saved alias does not always match the slug the old links contain.
    // Index the original permalink slug as well.
    $meta = json_decode($a['metadata'], true);
    if (!empty($meta['wp_link'])) {
        $sourceSlug = strtolower(basename(rtrim(parse_url($meta['wp_link'], PHP_URL_PATH) ?: '', '/')));
        if ($sourceSlug !== '') {
            $urlBySlug[$sourceSlug] = $url;
        }
    }
}

out('Articles with a route: ' . count($urlByArticle) . '  without: ' . $noRoute);

// The English slugs resolve to the same article, under the /en prefix.
$pairs = is_file(BASE . '/data/pairs.json')
    ? json_decode(file_get_contents(BASE . '/data/pairs.json'), true)
    : [];

$byWpId = [];
foreach ($articles as $a) {
    $meta = json_decode($a['metadata'], true);
    if (!empty($meta['wp_id'])) {
        $byWpId[(int) $meta['wp_id']] = (int) $a['id'];
    }
}

foreach ($pairs as $pair) {
    $joomlaId = $byWpId[$pair['ms_wp_id']] ?? null;
    if ($joomlaId && isset($urlByArticle[$joomlaId])) {
        $urlBySlug[strtolower($pair['en_slug'])] = '/en' . $urlByArticle[$joomlaId];
    }
}

out('Slug lookup entries: ' . count($urlBySlug));

/* ── Rewrite ─────────────────────────────────────────────────────────────── */

$pattern = '#https?://(?:www\.)?lkim\.gov\.my(/[^\s"\'<>\\\\)]*)?#i';

$changed    = 0;
$replaced   = 0;
$unresolved = [];

foreach ($articles as $a) {
    $dirty = false;

    foreach (['introtext', 'fulltext'] as $field) {
        $before = $a[$field];

        if ($before === '' || stripos($before, 'lkim.gov.my') === false) {
            continue;
        }

        $after = preg_replace_callback($pattern, function ($m) use (&$replaced, &$unresolved, $urlBySlug, $a) {
            $path = $m[1] ?? '/';

            // Media already points at /images; only page links remain.
            if (str_contains(strtolower($path), '/wp-content/')) {
                return $m[0];
            }

            // Lightbox plugins write hrefs relative to the request URI, so the
            // gallery pages came back from the REST API carrying our own API
            // URL plus a "#!mg_ld_123" fragment. Keep the fragment, drop the URL.
            if (str_contains(strtolower($path), '/wp-json/')) {
                $replaced++;
                $hash = strstr($path, '#');
                return $hash === false ? '#' : $hash;
            }

            $slug = strtolower(trim(preg_replace('#^/en/#i', '/', $path), '/'));
            $slug = $slug === '' ? '' : basename($slug);

            if ($slug !== '' && isset($urlBySlug[$slug])) {
                $replaced++;
                return $urlBySlug[$slug];
            }

            if ($slug === '') {
                $replaced++;
                return '/';
            }

            $unresolved[] = [$a['id'], $a['alias'], $m[0]];
            return $m[0];
        }, $before);

        if ($after !== $before) {
            $a[$field] = $after;
            $dirty     = true;
        }
    }

    if (!$dirty) {
        continue;
    }

    $changed++;

    if ($dryRun) {
        continue;
    }

    $db->setQuery(
        $db->getQuery(true)->update($db->quoteName('#__content'))
            ->set($db->quoteName('introtext') . ' = ' . $db->quote($a['introtext']))
            ->set($db->quoteName('fulltext') . ' = ' . $db->quote($a['fulltext']))
            ->where($db->quoteName('id') . ' = ' . (int) $a['id'])
    )->execute();
}

/* ── The same treatment for Falang's translated bodies ──────────────────── */

$falangChanged = 0;

foreach ($db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'value']))->from($db->quoteName('#__falang_content'))
        ->where($db->quoteName('reference_table') . ' = ' . $db->quote('content'))
        ->where($db->quoteName('reference_field') . ' IN (' . $db->quote('introtext') . ',' . $db->quote('fulltext') . ')')
        ->where($db->quoteName('value') . ' LIKE ' . $db->quote('%lkim.gov.my%'))
)->loadAssocList() as $row) {
    $after = preg_replace_callback($pattern, function ($m) use (&$replaced, $urlBySlug) {
        $path = $m[1] ?? '/';

        if (str_contains(strtolower($path), '/wp-content/')) {
            return $m[0];
        }

        $slug = strtolower(trim(preg_replace('#^/en/#i', '/', $path), '/'));
        $slug = $slug === '' ? '' : basename($slug);

        if ($slug !== '' && isset($urlBySlug[$slug])) {
            $replaced++;
            return $urlBySlug[$slug];
        }

        return $m[0];
    }, $row['value']);

    if ($after === $row['value'] || $dryRun) {
        continue;
    }

    $db->setQuery(
        $db->getQuery(true)->update($db->quoteName('#__falang_content'))
            ->set($db->quoteName('value') . ' = ' . $db->quote($after))
            ->where($db->quoteName('id') . ' = ' . (int) $row['id'])
    )->execute();

    $falangChanged++;
}

csv_write(BASE . '/reports/unresolved-links.csv', ['article_id', 'alias', 'url'], $unresolved);

out('');
out('Articles rewritten:      ' . $changed . ($dryRun ? ' (dry run)' : ''));
out('Translations rewritten:  ' . $falangChanged);
out('Links replaced:          ' . $replaced);
out('Links still unresolved:  ' . count($unresolved) . ' (reports/unresolved-links.csv)');
