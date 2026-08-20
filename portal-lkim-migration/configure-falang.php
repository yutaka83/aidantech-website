<?php
/**
 * Phase 8b: wire up the bilingual layer.
 *
 * Falang keeps a single content tree and stores English as a translation
 * layer, so the Malay articles imported in phase 4 stay the canonical records.
 *
 * The one non-obvious step: the core Language Filter plugin has to be ordered
 * BEFORE Falang's driver plugin. With the reverse order the driver runs first,
 * the filter then resets the active language, and menu items silently refuse
 * to translate.
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

/* ── Content languages ───────────────────────────────────────────────────── */

out('Content languages');

$languages = [
    'ms-MY' => ['title' => 'Bahasa Melayu', 'titleNative' => 'Bahasa Melayu', 'sef' => 'ms', 'ordering' => 1],
    'en-GB' => ['title' => 'English',       'titleNative' => 'English',       'sef' => 'en', 'ordering' => 2],
];

foreach ($languages as $code => $meta) {
    $db->setQuery(
        $db->getQuery(true)->update($db->quoteName('#__languages'))
            ->set($db->quoteName('title') . ' = ' . $db->quote($meta['title']))
            ->set($db->quoteName('title_native') . ' = ' . $db->quote($meta['titleNative']))
            ->set($db->quoteName('sef') . ' = ' . $db->quote($meta['sef']))
            ->set($db->quoteName('ordering') . ' = ' . (int) $meta['ordering'])
            ->set($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('lang_code') . ' = ' . $db->quote($code))
    )->execute();

    out('  ' . $code . ' -> /' . $meta['sef'] . '/  published');
}

/* ── Plugins ─────────────────────────────────────────────────────────────── */

out('');
out('Plugins');

/**
 * Enable a plugin and give it an explicit ordering within its folder.
 */
function set_plugin(DatabaseInterface $db, string $element, string $folder, int $enabled, ?int $ordering = null, ?array $params = null): void
{
    $query = $db->getQuery(true)->update($db->quoteName('#__extensions'))
        ->set($db->quoteName('enabled') . ' = ' . (int) $enabled)
        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
        ->where($db->quoteName('element') . ' = ' . $db->quote($element))
        ->where($db->quoteName('folder') . ' = ' . $db->quote($folder));

    if ($ordering !== null) {
        $query->set($db->quoteName('ordering') . ' = ' . (int) $ordering);
    }

    if ($params !== null) {
        $query->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)));
    }

    $db->setQuery($query)->execute();

    out(sprintf('  %-18s %s%s', $element, $enabled ? 'enabled' : 'disabled', $ordering !== null ? "  ordering=$ordering" : ''));
}

// Language Filter must sort ahead of falangdriver — see the note at the top.
set_plugin($db, 'languagefilter', 'system', 1, 1, [
    'detect_browser'        => '0',
    'automatic_change'      => '1',
    'item_associations'     => '0',   // Falang carries the association, not core
    'remove_default_prefix' => '1',
    'lang_cookie'           => '0',
    'alternate_meta'        => '1',
    'xdefault'              => '1',
    'xdefault_language'     => 'ms-MY',
]);

set_plugin($db, 'falangdriver', 'system', 1, 2);
set_plugin($db, 'falangquickjump', 'system', 1, 3);
set_plugin($db, 'falangcf', 'system', 1, 4);

/* ── Language switcher module ────────────────────────────────────────────── */

out('');
out('Language switcher');

$note     = 'lkim:langswitcher';
$existing = $db->setQuery(
    $db->getQuery(true)->select('id')->from($db->quoteName('#__modules'))
        ->where($db->quoteName('note') . ' = ' . $db->quote($note))
)->loadResult();

$params = json_encode([
    'show_name'  => 1,
    'full_name'  => 1,
    'image'      => 0,
    'inline'     => 1,
    'show_active' => 1,
    'dropdown'   => 0,
    'layout'     => '_:default',
]);

if ($existing) {
    $db->setQuery(
        $db->getQuery(true)->update($db->quoteName('#__modules'))
            ->set($db->quoteName('published') . ' = 1')
            ->set($db->quoteName('position') . ' = ' . $db->quote('topbar'))
            ->set($db->quoteName('params') . ' = ' . $db->quote($params))
            ->where($db->quoteName('id') . ' = ' . (int) $existing)
    )->execute();

    $moduleId = (int) $existing;
    out('  updated module #' . $moduleId);
} else {
    $db->setQuery(
        $db->getQuery(true)->insert($db->quoteName('#__modules'))
            ->columns($db->quoteName([
                'title', 'note', 'content', 'ordering', 'position', 'published',
                'module', 'access', 'showtitle', 'params', 'client_id', 'language',
            ]))
            ->values(implode(',', [
                $db->quote('Bahasa'),
                $db->quote($note),
                $db->quote(''),
                1,
                $db->quote('topbar'),
                1,
                $db->quote('mod_falang'),
                1,
                0,
                $db->quote($params),
                0,
                $db->quote('*'),
            ]))
    )->execute();

    $moduleId = (int) $db->insertid();
    out('  created module #' . $moduleId);
}

// Assign to every page.
$db->setQuery(
    $db->getQuery(true)->delete($db->quoteName('#__modules_menu'))
        ->where($db->quoteName('moduleid') . ' = ' . $moduleId)
)->execute();

$db->setQuery(
    $db->getQuery(true)->insert($db->quoteName('#__modules_menu'))
        ->columns($db->quoteName(['moduleid', 'menuid']))
        ->values($moduleId . ', 0')
)->execute();

// Falang's installer drops its own switcher into sidebar-right. Ours lives in
// the header's accessibility bar, so retire the duplicate.
$db->setQuery(
    $db->getQuery(true)->update($db->quoteName('#__modules'))
        ->set($db->quoteName('published') . ' = 0')
        ->where($db->quoteName('module') . ' = ' . $db->quote('mod_falang'))
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('id') . ' <> ' . $moduleId)
)->execute();

out('  duplicate installer switcher unpublished');

/* ── Falang component defaults ───────────────────────────────────────────── */

out('');
out('Falang component');

$row = $db->setQuery(
    $db->getQuery(true)->select(['extension_id', 'params'])->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_falang'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
)->loadAssoc();

if ($row) {
    $params = json_decode($row['params'], true) ?: [];
    $params = array_merge($params, [
        'show_list'            => '1',
        'show_form'            => '1',
        'copy_images_and_urls' => '1',
        'copy_custom_fields'   => '1',
        'reorderplugin'        => '1',
        'debug'                => '0',
    ]);

    $db->setQuery(
        $db->getQuery(true)->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
            ->where($db->quoteName('extension_id') . ' = ' . (int) $row['extension_id'])
    )->execute();

    out('  defaults applied');
}

out('');
out('Done.');
