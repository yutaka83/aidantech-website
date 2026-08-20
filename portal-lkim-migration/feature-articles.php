<?php
/**
 * The Home menu item is Joomla's Featured Articles view, and nothing was
 * featured, so the component area under the homepage bands came up empty.
 * Feature the most recent Berita Terkini items - the equivalent of the source
 * site's "Sorotan Peristiwa" strip.
 */

const _JEXEC = 1;

define('JPATH_BASE', 'C:\Users\hiday\Herd\portal-lkim');
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

$db    = $container->get(DatabaseInterface::class);
$count = (int) (getopt('', ['count::'])['count'] ?? 12);

$catId = (int) $db->setQuery(
    $db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName('#__categories'))
        ->where($db->quoteName('path') . ' = ' . $db->quote('berita/berita-terkini'))
)->loadResult();

$ids = $db->setQuery(
    $db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName('#__content'))
        ->where($db->quoteName('catid') . ' = ' . $catId)
        ->where($db->quoteName('state') . ' = 1')
        ->where($db->quoteName('language') . ' <> ' . $db->quote('en-GB'))
        ->order($db->quoteName('created') . ' DESC'),
    0,
    $count
)->loadColumn();

if (!$ids) {
    out('No articles found in berita/berita-terkini.');
    exit(1);
}

// Clear any previous selection so re-running does not accumulate.
$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__content_frontpage')))->execute();
$db->setQuery(
    $db->getQuery(true)->update($db->quoteName('#__content'))
        ->set($db->quoteName('featured') . ' = 0')
        ->where($db->quoteName('featured') . ' = 1')
)->execute();

$insert = $db->getQuery(true)->insert($db->quoteName('#__content_frontpage'))
    ->columns($db->quoteName(['content_id', 'ordering', 'featured_up', 'featured_down']));

foreach ($ids as $i => $id) {
    $insert->values((int) $id . ', ' . ($i + 1) . ', NULL, NULL');
}

$db->setQuery($insert)->execute();

$db->setQuery(
    $db->getQuery(true)->update($db->quoteName('#__content'))
        ->set($db->quoteName('featured') . ' = 1')
        ->whereIn($db->quoteName('id'), array_map('intval', $ids))
)->execute();

out('Featured ' . count($ids) . ' articles from berita/berita-terkini.');
