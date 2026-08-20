<?php
/**
 * Phase 7b: portal-wide settings that are tedious to click through - default
 * language, com_content display options, and retiring the stock modules the
 * fresh install ships with.
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

/* ── Default language: Bahasa Melayu, front end and admin ───────────────── */

out('Language');

foreach ([0 => 'site', 1 => 'administrator'] as $clientId => $label) {
    $db->setQuery(
        $db->getQuery(true)->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('language'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('ms-MY'))
            ->where($db->quoteName('client_id') . ' = ' . (int) $clientId)
    )->execute();
}

// The site default lives in #__languages, and the config file mirrors it.
$db->setQuery(
    $db->getQuery(true)->update($db->quoteName('#__languages'))
        ->set($db->quoteName('published') . ' = 1')
        ->where($db->quoteName('lang_code') . ' = ' . $db->quote('ms-MY'))
)->execute();

// Two places have to agree: configuration.php and com_languages' params. The
// latter is what the Language Manager actually reads, and a fresh install has
// no $language line at all, so insert rather than replace when it is missing.
$configFile = JPATH_BASE . '/configuration.php';
$config     = file_get_contents($configFile);

foreach (['language' => "'ms-MY'", 'offset' => "'Asia/Kuala_Lumpur'"] as $key => $value) {
    $pattern = '/public \$' . $key . '\s*=\s*[^;]*;/';
    $line    = 'public $' . $key . ' = ' . $value . ';';

    $config = preg_match($pattern, $config)
        ? preg_replace($pattern, $line, $config, 1)
        : preg_replace('/(class JConfig\s*\{)/', "$1\n\t" . $line, $config, 1);
}

file_put_contents($configFile, $config);

$db->setQuery(
    $db->getQuery(true)->update($db->quoteName('#__extensions'))
        ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode([
            'administrator' => 'en-GB',
            'site'          => 'ms-MY',
        ])))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_languages'))
)->execute();

out('  site default language -> ms-MY (admin stays en-GB)');

/* ── com_content display options ─────────────────────────────────────────── */

out('');
out('Article display');

$row = $db->setQuery(
    $db->getQuery(true)->select(['extension_id', 'params'])->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_content'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
)->loadAssoc();

$params = json_decode($row['params'], true) ?: [];

// A government portal wants the content, not the blog furniture.
$params = array_merge($params, [
    'show_title'            => '1',
    'link_titles'           => '1',
    'show_intro'            => '1',
    'info_block_position'   => '0',
    'info_block_show_title' => '0',
    'show_category'         => '0',
    'show_parent_category'  => '0',
    'show_associations'     => '0',
    'show_author'           => '0',
    'show_create_date'      => '0',
    'show_modify_date'      => '1',
    'show_publish_date'     => '1',
    'show_item_navigation'  => '1',
    'show_hits'             => '0',
    'show_tags'             => '1',
    'show_noauth'           => '0',
    'show_readmore'         => '1',
    'show_readmore_title'   => '0',
    'show_icons'            => '0',
    'show_print_icon'       => '1',
    'show_email_icon'       => '1',
    'show_vote'             => '0',
    'record_hits'           => '1',
    'show_page_heading'     => '0',
    'num_leading_articles'  => '1',
    'num_intro_articles'    => '6',
    'num_links'             => '8',
    'multi_column_order'    => '0',
    'show_pagination'       => '2',
    'show_pagination_results' => '1',
    'filter_field'          => 'hide',
    'show_headings'         => '0',
    'sef_advanced_link'     => '1',
]);

$db->setQuery(
    $db->getQuery(true)->update($db->quoteName('#__extensions'))
        ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
        ->where($db->quoteName('extension_id') . ' = ' . (int) $row['extension_id'])
)->execute();

out('  author, hits and category badges hidden; modify date shown');

/* ── The Home menu item ──────────────────────────────────────────────────── */

out('');
out('Homepage');

$homeId = (int) $db->setQuery(
    $db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName('#__menu'))
        ->where($db->quoteName('home') . ' = 1')
        ->where($db->quoteName('client_id') . ' = 0')
)->loadResult();

if ($homeId) {
    // The homepage's substance is in the module bands; the featured-articles
    // component below them should read as a news strip, not a blog.
    $db->setQuery(
        $db->getQuery(true)->update($db->quoteName('#__menu'))
            ->set($db->quoteName('title') . ' = ' . $db->quote('Laman Utama'))
            ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode([
                'show_page_heading'    => '0',
                'num_leading_articles' => '0',
                'num_intro_articles'   => '6',
                'num_links'            => '0',
                'multi_column_order'   => '0',
                'show_publish_date'    => '0',
                'show_modify_date'     => '0',
                'show_create_date'     => '0',
                'show_author'          => '0',
                'show_hits'            => '0',
                'show_category'        => '0',
                'info_block_position'  => '0',
                'show_intro'           => '1',
                'show_readmore'        => '1',
                'menu-meta_description' => 'Portal Rasmi Lembaga Kemajuan Ikan Malaysia (LKIM)',
            ])))
            ->where($db->quoteName('id') . ' = ' . $homeId)
    )->execute();

    out('  Home renamed to "Laman Utama", info block hidden on teasers');
}

/* ── Retire the stock modules the fresh install ships with ──────────────── */

out('');
out('Stock modules');

$retired = $db->setQuery(
    $db->getQuery(true)->update($db->quoteName('#__modules'))
        ->set($db->quoteName('published') . ' = 0')
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('note') . ' NOT LIKE ' . $db->quote('lkim:%'))
)->execute();

out('  stock site modules unpublished (LKIM modules keep their lkim: note)');

/* ── Site metadata ───────────────────────────────────────────────────────── */

$app->getConfig();
out('');
out('Done.');
