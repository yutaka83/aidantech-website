<?php
/**
 * Phase 5: rebuild the portal menus from the captured live navigation.
 *
 * Three menus are produced:
 *   mainmenu    the live IA, one for one (headings + article items)
 *   footermenu  the SPLaSK-required policy and help links
 *   hiddenmenu  category views for news/archive, so those pages have a
 *               routable home and clean SEF URLs even though the live site
 *               only reaches them from homepage modules
 *
 * Idempotent: every generated item carries note="lkim:<key>" and is matched on
 * that, so re-running updates in place instead of duplicating.
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
    define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_menus');
    define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_menus');
    define('JPATH_COMPONENT_SITE', JPATH_SITE . '/components/com_menus');
}

/* ── Reference data ──────────────────────────────────────────────────────── */

$contentId = (int) $db->setQuery(
    $db->getQuery(true)->select('extension_id')->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_content'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
)->loadResult();

$routeMap = json_decode(file_get_contents(BASE . '/data/route-map.json'), true);
$menuTree = json_decode(file_get_contents(BASE . '/data/menu.json'), true);
$map      = require __DIR__ . '/map.php';

/** category path => joomla id */
$catIds = [];
foreach ($db->setQuery(
    $db->getQuery(true)->select(['id', 'alias', 'parent_id', 'path'])
        ->from($db->quoteName('#__categories'))
        ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
)->loadAssocList() as $row) {
    $catIds[$row['path']] = (int) $row['id'];
}

$menuFactory = $app->bootComponent('com_menus')->getMVCFactory();

/* ── Menu type helpers ───────────────────────────────────────────────────── */

function ensure_menutype(DatabaseInterface $db, string $type, string $title, string $description): void
{
    $exists = $db->setQuery(
        $db->getQuery(true)->select('id')->from($db->quoteName('#__menu_types'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote($type))
    )->loadResult();

    if ($exists) {
        return;
    }

    $db->setQuery(
        $db->getQuery(true)->insert($db->quoteName('#__menu_types'))
            ->columns($db->quoteName(['menutype', 'title', 'description', 'client_id']))
            ->values(implode(',', [$db->quote($type), $db->quote($title), $db->quote($description), 0]))
    )->execute();

    out("  + menu type: $type");
}

ensure_menutype($db, 'mainmenu', 'Menu Utama', 'Navigasi utama portal LKIM');
ensure_menutype($db, 'footermenu', 'Menu Pengaki', 'Pautan dasar dan bantuan (SPLaSK)');
ensure_menutype($db, 'hiddenmenu', 'Menu Tersembunyi', 'Laluan untuk paparan kategori berita dan arkib');

/* ── Item creation ───────────────────────────────────────────────────────── */

$created = 0;
$updated = 0;
$failed  = 0;
$skipped = [];

/**
 * Create or update one menu item, keyed on its note.
 *
 * @return int|null the item id
 */
/** Every key this run produced, so stale items from earlier runs can be removed. */
$liveKeys = [];

function put_item(
    $menuFactory,
    DatabaseInterface $db,
    string $key,
    array $data,
    int &$created,
    int &$updated,
    int &$failed
): ?int {
    $GLOBALS['liveKeys']['lkim:' . $key] = true;

    $note = 'lkim:' . $key;

    $existing = $db->setQuery(
        $db->getQuery(true)->select('id')->from($db->quoteName('#__menu'))
            ->where($db->quoteName('note') . ' = ' . $db->quote($note))
    )->loadResult();

    $model = $menuFactory->createModel('Item', 'Administrator', ['ignore_request' => true]);

    $payload = array_merge([
        'id'           => $existing ? (int) $existing : 0,
        'note'         => $note,
        'published'    => 1,
        'access'       => 1,
        'language'     => '*',
        'browserNav'   => 0,
        'client_id'    => 0,
        'template_style_id' => 0,
        'params'       => '{}',
        'img'          => '',
        'home'         => 0,
    ], $data);

    if (!$model->save($payload)) {
        $failed++;
        out('  ! ' . $key . ': ' . $model->getError());
        return null;
    }

    $existing ? $updated++ : $created++;

    return (int) $model->getItem()->id;
}

/* ── Main menu, from the captured live tree ─────────────────────────────── */

out('');
out('Main menu');

$walk = function (array $items, int $parentId, array $trail) use (
    &$walk, $menuFactory, $db, $routeMap, $contentId, $map,
    &$created, &$updated, &$failed, &$skipped
) {
    $order = 1;

    foreach ($items as $item) {
        $label = html_entity_decode($item['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $slug  = $item['slug'];
        $key   = implode('/', array_merge($trail, [$slug !== '' ? $slug : slugify($label)]));

        if ($slug === 'home') {
            // Joomla's own default Home item stays as the site root.
            continue;
        }

        $data = [
            'menutype'  => 'mainmenu',
            'title'     => $label,
            'alias'     => slugify($slug !== '' ? $slug : $label),
            'parent_id' => $parentId,
        ];

        if ($slug === '') {
            // A label with no target: a grouping heading.
            $data['type'] = 'heading';
            $data['link'] = '';
        } elseif (isset($routeMap[$slug])) {
            $article      = $routeMap[$slug];
            $data['type'] = 'component';
            $data['link'] = 'index.php?option=com_content&view=article&id=' . $article['id'];
            $data['component_id'] = $contentId;
        } elseif (in_array($slug, $map['known_broken_targets'], true)) {
            // Broken on the live site too - keep the label, drop the dead link.
            $data['type'] = 'heading';
            $data['link'] = '';
            $skipped[]    = [$label, $slug, 'target page does not exist on the source site'];
        } else {
            $data['type'] = 'url';
            $data['link'] = $item['url'];
            $skipped[]    = [$label, $slug, 'no imported article, left as an external link'];
        }

        $id = put_item($menuFactory, $db, $key, $data, $created, $updated, $failed);

        out(sprintf('  %s%-58s %s', str_repeat('  ', count($trail)), mb_substr($label, 0, 58), $data['type']));

        if ($id && $item['children']) {
            $walk($item['children'], $id, array_merge($trail, [$slug !== '' ? $slug : slugify($label)]));
        }

        $order++;
    }
};

function slugify(string $s): string
{
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
    return trim($s, '-') ?: 'item';
}

$walk($menuTree, 1, []);

/* ── Hidden menu: routable category views ───────────────────────────────── */

out('');
out('Hidden menu (category routes)');

// Every category gets a routable item, not just the news ones: an article whose
// category has no menu item falls back to Joomla's /component/content/... URL,
// which is ugly and unstable. These live in a hidden menu, so they add routes
// without appearing in the navigation.
$categoryViews = [
    'berita'                  => ['Berita & Pengumuman', 'blog'],
    'berita/pengumuman'       => ['Pengumuman', 'blog'],
    'berita/berita-terkini'   => ['Berita Terkini', 'blog'],
    'berita/sebut-harga'      => ['Sebut Harga', 'list'],
    'berita/tender'           => ['Tender', 'list'],
    'arkib'                   => ['Arkib', 'list'],
    'arkib/arkib-pengumuman'  => ['Arkib Pengumuman', 'list'],
    'arkib/arkib-sebut-harga' => ['Arkib Sebut Harga', 'list'],
    'arkib/arkib-berita'      => ['Arkib Berita', 'blog'],
    'galeri'                  => ['Galeri', 'blog'],
    'lain-lain'               => ['Lain-lain', 'list'],
    'info-lkim'               => ['Info LKIM', 'blog'],
    'info-lkim/perutusan'     => ['Perutusan', 'blog'],
    'info-lkim/profil'        => ['Profil', 'blog'],
    'info-lkim/organisasi'    => ['Organisasi', 'blog'],
    'perkhidmatan'            => ['Perkhidmatan', 'blog'],
    'perkhidmatan/institusi-nelayan'          => ['Institusi Nelayan', 'blog'],
    'perkhidmatan/bantuan-masyarakat-nelayan' => ['Bantuan Kepada Masyarakat Nelayan', 'blog'],
    'perkhidmatan/pemasaran-ikan'             => ['Pemasaran Ikan', 'blog'],
    'perkhidmatan/pembangunan-infrastruktur'  => ['Pembangunan Infrastruktur', 'blog'],
    'perkhidmatan/kawalselia-penguatkuasaan'  => ['Kawalselia Pendaratan Ikan dan Penguatkuasaan', 'blog'],
    'perkhidmatan/industri-asas-tani'         => ['Pembangunan Industri Asas Tani', 'blog'],
    'perkhidmatan/agrotourism'                => ['Agrotourism', 'blog'],
    'hubungi-kami'            => ['Hubungi Kami', 'blog'],
];

foreach ($categoryViews as $path => [$title, $layout]) {
    if (!isset($catIds[$path])) {
        out('  ! missing category: ' . $path);
        continue;
    }

    $link = $layout === 'blog'
        ? 'index.php?option=com_content&view=category&layout=blog&id=' . $catIds[$path]
        : 'index.php?option=com_content&view=category&id=' . $catIds[$path];

    put_item($menuFactory, $db, 'cat/' . $path, [
        'menutype'     => 'hiddenmenu',
        'title'        => $title,
        // "k-" for kategori: root-level menu aliases must be unique across
        // every menu, and the main menu already owns several of these names.
        'alias'        => 'k-' . str_replace('/', '-', $path),
        'parent_id'    => 1,
        'type'         => 'component',
        'link'         => $link,
        'component_id' => $contentId,
    ], $created, $updated, $failed);

    out(sprintf('  %-30s %s', $path, $layout));
}

// Smart Search needs a menu item of its own, otherwise every result page is
// served from an unrouted query string.
$finderId = (int) $db->setQuery(
    $db->getQuery(true)->select($db->quoteName('extension_id'))->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_finder'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
)->loadResult();

put_item($menuFactory, $db, 'cari', [
    'menutype'     => 'hiddenmenu',
    'title'        => 'Carian',
    'alias'        => 'carian',
    'parent_id'    => 1,
    'type'         => 'component',
    'link'         => 'index.php?option=com_finder&view=search',
    'component_id' => $finderId,
], $created, $updated, $failed);

out('  carian (Smart Search)');

/* ── Footer menu: the SPLaSK checklist links ────────────────────────────── */

out('');
out('Footer menu');

// Aliases are prefixed because Joomla requires them to be unique among all
// root-level menu items, and the main menu already owns several of these names.
$footer = [
    'pk-hubungi-kami'      => ['Hubungi Kami', 'hubungi-kami'],
    'pk-maklum-balas'      => ['Aduan / Pertanyaan / Cadangan', 'aduanpertanyaancadangan-awam'],
    'pk-soalan-lazim'      => ['Soalan Lazim (FAQ)', 'soalan-lazim'],
    'pk-pautan'            => ['Pautan', 'pautan'],
    'pk-peta-laman'        => ['Peta Laman', 'peta-laman'],
    'pk-terma-syarat'      => ['Terma & Syarat', 'terma-syarat'],
    'pk-dasar-keselamatan' => ['Dasar Keselamatan', 'dasar-keselamatan-ict'],
    'pk-dasar-privasi'     => ['Dasar Privasi', 'dasar-privasi'],
    'pk-penafian'          => ['Penafian', 'penafian'],
];

foreach ($footer as $alias => [$title, $sourceSlug]) {
    $data = [
        'menutype'  => 'footermenu',
        'title'     => $title,
        'alias'     => $alias,
        'parent_id' => 1,
    ];

    if ($sourceSlug !== null && isset($routeMap[$sourceSlug])) {
        $data['type']         = 'component';
        $data['link']         = 'index.php?option=com_content&view=article&id=' . $routeMap[$sourceSlug]['id'];
        $data['component_id'] = $contentId;
    } else {
        // No source article yet - a heading keeps the required label visible
        // without shipping a dead link. Point it at content once written.
        $data['type'] = 'heading';
        $data['link'] = '';
        $skipped[]    = [$title, $alias, 'footer item needs an article to be written'];
    }

    put_item($menuFactory, $db, 'footer/' . $alias, $data, $created, $updated, $failed);
    out(sprintf('  %-24s %s', $title, $data['type']));
}

/* ── Remove items this run no longer produces ───────────────────────────── */

$stale = array_filter(
    $db->setQuery(
        $db->getQuery(true)->select($db->quoteName(['id', 'title', 'note']))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('note') . ' LIKE ' . $db->quote('lkim:%'))
    )->loadAssocList(),
    fn($row) => !isset($liveKeys[$row['note']])
);

if ($stale) {
    out('');
    out('Removing items from earlier runs');

    foreach ($stale as $row) {
        // Trash first, then delete: the model takes $pks by reference, so the
        // ids have to live in a variable rather than an inline array.
        $model = $menuFactory->createModel('Item', 'Administrator', ['ignore_request' => true]);
        $ids   = [(int) $row['id']];
        $model->publish($ids, -2);

        $ids = [(int) $row['id']];
        $model->delete($ids);
        out(sprintf('  - %-40s %s', mb_substr($row['title'], 0, 40), $row['note']));
    }
}

csv_write(BASE . '/reports/menu-gaps.csv', ['label', 'slug', 'reason'], $skipped);

out('');
out('created: ' . $created . '  updated: ' . $updated . '  failed: ' . $failed);
out('gaps written to reports/menu-gaps.csv (' . count($skipped) . ')');
