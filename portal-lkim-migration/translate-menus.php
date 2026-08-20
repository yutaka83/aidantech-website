<?php
/**
 * Phase 8d: translate the navigation labels.
 *
 * The Malay and English mega-menus on the source site mirror each other item
 * for item, so the English label for a menu item is simply the label at the
 * same position in the English tree. That is far more reliable here than the
 * page-level hreflang links, which the source site only fills in sporadically.
 *
 * Footer and hidden-menu labels are translated from a small hand-written table
 * because they have no counterpart on the source site.
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

$languageId = (int) $db->setQuery(
    $db->getQuery(true)->select($db->quoteName('lang_id'))->from($db->quoteName('#__languages'))
        ->where($db->quoteName('lang_code') . ' = ' . $db->quote('en-GB'))
)->loadResult();

/* ── Walk both menu trees in parallel ────────────────────────────────────── */

$bm = json_decode(file_get_contents(BASE . '/data/menu.json'), true);
$en = json_decode(file_get_contents(BASE . '/data/menu-en.json'), true);

/**
 * Index a captured menu tree by position path, mirroring build-menus.php's key
 * scheme so the results line up with the notes stored on the Joomla items.
 */
function index_tree(array $items, array $trail = [], array &$out = []): array
{
    foreach ($items as $i => $item) {
        $path = array_merge($trail, [$i]);
        $out[implode('.', $path)] = html_entity_decode($item['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($item['children']) {
            index_tree($item['children'], $path, $out);
        }
    }

    return $out;
}

$bmByPos = index_tree($bm);
$enByPos = index_tree($en);

/**
 * Rebuild the note keys build-menus.php assigned, so a Joomla item can be
 * matched back to its position in the captured tree.
 */
function slugify(string $s): string
{
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = mb_strtolower(trim($s));
    return trim(preg_replace('/[^a-z0-9]+/u', '-', $s), '-') ?: 'item';
}

$keyByPos = [];

$walk = function (array $items, array $trail, array $keyTrail) use (&$walk, &$keyByPos) {
    foreach ($items as $i => $item) {
        if ($item['slug'] === 'home') {
            continue;
        }

        $path    = array_merge($trail, [$i]);
        $segment = $item['slug'] !== '' ? $item['slug'] : slugify($item['label']);
        $keyPath = array_merge($keyTrail, [$segment]);

        $keyByPos[implode('.', $path)] = implode('/', $keyPath);

        if ($item['children']) {
            $walk($item['children'], $path, $keyPath);
        }
    }
};

$walk($bm, [], []);

/* ── Labels we supply ourselves ──────────────────────────────────────────── */

$manual = [
    'footer/pk-hubungi-kami'      => 'Contact Us',
    'footer/pk-maklum-balas'      => 'Complaints / Enquiries / Suggestions',
    'footer/pk-soalan-lazim'      => 'FAQ',
    'footer/pk-pautan'            => 'Links',
    'footer/pk-peta-laman'        => 'Sitemap',
    'footer/pk-terma-syarat'      => 'Terms & Conditions',
    'footer/pk-dasar-keselamatan' => 'Security Policy',
    'footer/pk-dasar-privasi'     => 'Privacy Policy',
    'footer/pk-penafian'          => 'Disclaimer',
    'cat/berita'                  => 'News & Announcements',
    'cat/berita/pengumuman'       => 'Announcements',
    'cat/berita/berita-terkini'   => 'Latest News',
    'cat/berita/sebut-harga'      => 'Quotations',
    'cat/berita/tender'           => 'Tenders',
    'cat/arkib'                   => 'Archive',
    'cat/arkib/arkib-pengumuman'  => 'Announcement Archive',
    'cat/arkib/arkib-sebut-harga' => 'Quotation Archive',
    'cat/arkib/arkib-berita'      => 'News Archive',
    'cat/galeri'                  => 'Gallery',
    'cat/lain-lain'               => 'Other Pages',
    'cat/info-lkim'               => 'About LKIM',
    'cat/info-lkim/perutusan'     => 'Messages',
    'cat/info-lkim/profil'        => 'Profile',
    'cat/info-lkim/organisasi'    => 'Organisation',
    'cat/perkhidmatan'            => 'Services',
    'cat/perkhidmatan/institusi-nelayan'          => 'Fishermen Institutions',
    'cat/perkhidmatan/bantuan-masyarakat-nelayan' => 'Assistance to the Fishing Community',
    'cat/perkhidmatan/pemasaran-ikan'             => 'Fish Marketing',
    'cat/perkhidmatan/pembangunan-infrastruktur'  => 'Infrastructure Development',
    'cat/perkhidmatan/kawalselia-penguatkuasaan'  => 'Fish Landings Regulation and Enforcement',
    'cat/perkhidmatan/industri-asas-tani'         => 'Agro-Based Industry Development',
    'cat/perkhidmatan/agrotourism'                => 'Agrotourism',
    'cat/hubungi-kami'            => 'Contact Us',
];

/* ── Build note => English label ─────────────────────────────────────────── */

$translations = $manual;
$fromMenu     = 0;

foreach ($keyByPos as $pos => $key) {
    if (isset($enByPos[$pos]) && isset($bmByPos[$pos]) && $enByPos[$pos] !== $bmByPos[$pos]) {
        $translations[$key] = $enByPos[$pos];
        $fromMenu++;
    }
}

out('Labels from the English menu: ' . $fromMenu);
out('Labels supplied manually:     ' . count($manual));

/* ── Write Falang rows ───────────────────────────────────────────────────── */

$items = $db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'title', 'note']))
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('note') . ' LIKE ' . $db->quote('lkim:%'))
)->loadAssocList();

$db->setQuery(
    $db->getQuery(true)->delete($db->quoteName('#__falang_content'))
        ->where($db->quoteName('language_id') . ' = ' . $languageId)
        ->where($db->quoteName('reference_table') . ' = ' . $db->quote('menu'))
)->execute();

$written = 0;
$missing = [];
$insert  = $db->getQuery(true)->insert($db->quoteName('#__falang_content'))
    ->columns($db->quoteName([
        'language_id', 'reference_id', 'reference_table', 'reference_field',
        'value', 'original_value', 'original_text', 'modified', 'modified_by', 'published',
    ]));

$now = date('Y-m-d H:i:s');

foreach ($items as $item) {
    $key   = substr($item['note'], strlen('lkim:'));
    $label = $translations[$key] ?? null;

    if ($label === null) {
        $missing[] = [$item['id'], $item['title'], $key];
        continue;
    }

    $insert->values(implode(',', [
        $languageId,
        (int) $item['id'],
        $db->quote('menu'),
        $db->quote('title'),
        $db->quote($label),
        $db->quote(md5((string) $item['title'])),
        $db->quote(''),
        $db->quote($now),
        0,
        1,
    ]));

    $written++;
}

if ($written) {
    $db->setQuery($insert)->execute();
}

csv_write(BASE . '/reports/menu-untranslated.csv', ['menu_id', 'title', 'key'], $missing);

out('');
out('Menu labels translated: ' . $written);
out('Without a translation:  ' . count($missing) . ' (reports/menu-untranslated.csv)');

/* ── Module titles ───────────────────────────────────────────────────────── */

out('');
out('Module titles');

$moduleTitles = [
    'lkim:pengumuman'            => 'Announcements',
    'lkim:berita-terkini'        => 'Latest News',
    'lkim:sebut-harga'           => 'Quotations & Tenders',
    'lkim:perkhidmatan-online'   => 'Online Services',
    'lkim:arkib'                 => 'Archive',
    'lkim:kategori-perkhidmatan' => 'LKIM Services',
    'lkim:footer-alamat'         => 'Contact LKIM',
    'lkim:footer-pautan'         => 'Quick Links',
    'lkim:footer-info'           => 'Portal Information',
    'lkim:footer-sosial'         => 'Follow Us',
    'lkim:breadcrumbs'           => 'You are here',
    'lkim:search'                => 'Search',
    'lkim:mainmenu'              => 'Main Menu',
    'lkim:langswitcher'          => 'Language',
    'lkim:syndicate'             => 'RSS',
];

$db->setQuery(
    $db->getQuery(true)->delete($db->quoteName('#__falang_content'))
        ->where($db->quoteName('language_id') . ' = ' . $languageId)
        ->where($db->quoteName('reference_table') . ' = ' . $db->quote('modules'))
)->execute();

$modules = $db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'title', 'note']))
        ->from($db->quoteName('#__modules'))
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('note') . ' LIKE ' . $db->quote('lkim:%'))
)->loadAssocList();

$modInsert = $db->getQuery(true)->insert($db->quoteName('#__falang_content'))
    ->columns($db->quoteName([
        'language_id', 'reference_id', 'reference_table', 'reference_field',
        'value', 'original_value', 'original_text', 'modified', 'modified_by', 'published',
    ]));

$modWritten = 0;

foreach ($modules as $module) {
    $label = $moduleTitles[$module['note']] ?? null;

    if ($label === null) {
        continue;
    }

    $modInsert->values(implode(',', [
        $languageId,
        (int) $module['id'],
        $db->quote('modules'),
        $db->quote('title'),
        $db->quote($label),
        $db->quote(md5((string) $module['title'])),
        $db->quote(''),
        $db->quote($now),
        0,
        1,
    ]));

    $modWritten++;
}

if ($modWritten) {
    $db->setQuery($modInsert)->execute();
}

out('  translated: ' . $modWritten . ' of ' . count($modules));
