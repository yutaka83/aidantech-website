<?php
/**
 * Phase 9a: 301 the old WordPress permalinks at the matching Joomla routes.
 *
 * Every page on lkim.gov.my has been indexed and linked to for years. Without
 * these the migration would drop that traffic and break every external link,
 * so each source permalink gets a redirect record and com_redirect serves it.
 *
 * Idempotent: matched on old_url.
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

/* ── Routes, the same way relink.php derives them ───────────────────────── */

$articleRoute  = [];
$categoryRoute = [];

foreach ($db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'path', 'link']))
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

$articles = $db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'alias', 'catid', 'metadata']))
        ->from($db->quoteName('#__content'))
)->loadAssocList();

/* ── old path => new path ────────────────────────────────────────────────── */

$redirects = [];
$byWpId    = [];

foreach ($articles as $a) {
    $meta = json_decode($a['metadata'], true);

    if (empty($meta['wp_link'])) {
        continue;
    }

    $new = $articleRoute[(int) $a['id']]
        ?? (isset($categoryRoute[(int) $a['catid']]) ? $categoryRoute[(int) $a['catid']] . '/' . $a['alias'] : null);

    if ($new === null) {
        continue;
    }

    $old = rtrim(parse_url($meta['wp_link'], PHP_URL_PATH) ?: '', '/');

    if ($old === '' || $old === $new) {
        continue;
    }

    // WordPress permalinks end with a slash and the Redirect plugin matches the
    // request path literally, so store both forms.
    $redirects[$old]        = $new;
    $redirects[$old . '/']  = $new;

    $byWpId[(int) $meta['wp_id']] = ['id' => (int) $a['id'], 'new' => $new];
}

// English permalinks land on the /en/ view of the same article.
$pairs = is_file(BASE . '/data/pairs.json')
    ? json_decode(file_get_contents(BASE . '/data/pairs.json'), true)
    : [];

$resolved = json_decode(file_get_contents(BASE . '/data/resolved.json'), true);
$enLinks  = [];

foreach ($resolved['articles'] as $a) {
    if ($a['lang'] === 'en') {
        $enLinks[$a['wp_id']] = rtrim(parse_url($a['link'], PHP_URL_PATH) ?: '', '/');
    }
}

foreach ($pairs as $pair) {
    $target = $byWpId[$pair['ms_wp_id']] ?? null;
    $old    = $enLinks[$pair['en_wp_id']] ?? null;

    if ($target && $old) {
        $redirects[$old]       = '/en' . $target['new'];
        $redirects[$old . '/'] = '/en' . $target['new'];
    }
}

out('Redirects to install: ' . count($redirects));

/* ── Write ───────────────────────────────────────────────────────────────── */

$existing = [];
foreach ($db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'old_url']))->from($db->quoteName('#__redirect_links'))
)->loadAssocList() as $row) {
    $existing[$row['old_url']] = (int) $row['id'];
}

$created = 0;
$updated = 0;
$now     = date('Y-m-d H:i:s');
$batch   = [];

foreach ($redirects as $old => $new) {
    if (isset($existing[$old])) {
        $db->setQuery(
            $db->getQuery(true)->update($db->quoteName('#__redirect_links'))
                ->set($db->quoteName('new_url') . ' = ' . $db->quote($new))
                ->set($db->quoteName('published') . ' = 1')
                ->set($db->quoteName('header') . ' = 301')
                ->where($db->quoteName('id') . ' = ' . $existing[$old])
        )->execute();

        $updated++;
        continue;
    }

    // referer and hits are NOT NULL without defaults, so both are supplied.
    $batch[] = implode(',', [
        $db->quote($old),
        $db->quote($new),
        $db->quote(''),
        $db->quote('Migrated from lkim.gov.my'),
        0,
        $db->quote($now),
        $db->quote($now),
        1,
        301,
    ]);

    $created++;

    // MySQL packet limits: flush in chunks.
    if (count($batch) >= 200) {
        flush_batch($db, $batch);
        $batch = [];
    }
}

if ($batch) {
    flush_batch($db, $batch);
}

function flush_batch(DatabaseInterface $db, array $batch): void
{
    $query = $db->getQuery(true)->insert($db->quoteName('#__redirect_links'))
        ->columns($db->quoteName(['old_url', 'new_url', 'referer', 'comment', 'hits', 'created_date', 'modified_date', 'published', 'header']));

    foreach ($batch as $values) {
        $query->values($values);
    }

    $db->setQuery($query)->execute();
}

/* ── Turn the redirect handling on ──────────────────────────────────────── */

$db->setQuery(
    $db->getQuery(true)->update($db->quoteName('#__extensions'))
        ->set($db->quoteName('enabled') . ' = 1')
        ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode([
            'collect_urls'  => '1',
            'mode'          => '1',
            'defaulturl'    => '',
            'defaultmessage' => '',
        ])))
        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('redirect'))
        ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
)->execute();

out('created: ' . $created . '  updated: ' . $updated);
out('System - Redirect plugin enabled (404 collection on)');
