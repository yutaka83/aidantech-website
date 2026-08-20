<?php
/**
 * Check that every /images/... reference in the imported content actually
 * exists on disk. Writes reports/qa-media.csv.
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

$db = $container->get(DatabaseInterface::class);

$sources = [];

foreach ($db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'alias', 'introtext', 'fulltext']))
        ->from($db->quoteName('#__content'))
)->loadAssocList() as $row) {
    $sources[] = ['article ' . $row['id'], $row['alias'], $row['introtext'] . $row['fulltext']];
}

foreach ($db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'reference_id', 'value']))
        ->from($db->quoteName('#__falang_content'))
        ->where($db->quoteName('reference_table') . ' = ' . $db->quote('content'))
)->loadAssocList() as $row) {
    $sources[] = ['translation ' . $row['id'], 'article ' . $row['reference_id'], $row['value']];
}

$seen    = [];
$missing = [];
$checked = 0;

foreach ($sources as [$origin, $label, $html]) {
    if (!preg_match_all('#(?:src|href)="(/images/[^"]+)"#i', (string) $html, $m)) {
        continue;
    }

    foreach ($m[1] as $path) {
        $key = strtolower($path);

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $checked++;

        $file = JPATH_BASE . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($path));

        if (!is_file($file)) {
            $missing[] = [$origin, $label, $path];
        }
    }
}

csv_write(BASE . '/reports/qa-media.csv', ['origin', 'label', 'path'], $missing);

out('Distinct media references: ' . $checked);
out('Missing on disk:           ' . count($missing) . ' (reports/qa-media.csv)');
