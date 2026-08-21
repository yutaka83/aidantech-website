<?php
/**
 * Phase 7: lay out the portal's modules - navigation, search, the homepage
 * bands and the footer columns - using core modules only.
 *
 * Idempotent: modules are matched on a "lkim:<key>" note, so re-running
 * updates in place.
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
    define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_modules');
    define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_modules');
    define('JPATH_COMPONENT_SITE', JPATH_SITE . '/components/com_modules');
}

$moduleFactory = $app->bootComponent('com_modules')->getMVCFactory();

/* ── Reference data ──────────────────────────────────────────────────────── */

$catIds = [];
foreach ($db->setQuery(
    $db->getQuery(true)->select(['id', 'path'])->from($db->quoteName('#__categories'))
        ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
)->loadAssocList() as $row) {
    $catIds[$row['path']] = (int) $row['id'];
}

/* ── Helper ──────────────────────────────────────────────────────────────── */

$created = 0;
$updated = 0;
$failed  = 0;

function put_module($factory, DatabaseInterface $db, string $key, array $data, int &$c, int &$u, int &$f): ?int
{
    $note = 'lkim:' . $key;

    $existing = $db->setQuery(
        $db->getQuery(true)->select('id')->from($db->quoteName('#__modules'))
            ->where($db->quoteName('note') . ' = ' . $db->quote($note))
    )->loadResult();

    $model = $factory->createModel('Module', 'Administrator', ['ignore_request' => true]);

    $payload = array_merge([
        'id'        => $existing ? (int) $existing : 0,
        'note'      => $note,
        'published' => 1,
        'access'    => 1,
        'language'  => '*',
        'client_id' => 0,
        'showtitle' => 1,
        'content'   => '',
        'assignment' => 0,   // 0 = on all pages
        'assigned'  => [],
    ], $data);

    if (!$model->save($payload)) {
        $f++;
        out('  ! ' . $key . ': ' . $model->getError());
        return null;
    }

    $existing ? $u++ : $c++;
    out(sprintf('  %-28s %-22s %s', $key, $data['position'], $data['module']));

    return (int) $model->getItem()->id;
}

/** Restrict a module to the front page only. */
function homeOnly(DatabaseInterface $db): array
{
    $homeId = (int) $db->setQuery(
        $db->getQuery(true)->select('id')->from($db->quoteName('#__menu'))
            ->where($db->quoteName('home') . ' = 1')
            ->where($db->quoteName('client_id') . ' = 0')
    )->loadResult();

    return ['assignment' => 1, 'assigned' => [$homeId]];
}

$home = homeOnly($db);

/* ── Navigation and chrome ───────────────────────────────────────────────── */

out('Navigation');

put_module($moduleFactory, $db, 'mainmenu', [
    'title'    => 'Menu Utama',
    'module'   => 'mod_menu',
    'position' => 'menu',
    'showtitle' => 0,
    'ordering' => 1,
    'params'   => json_encode([
        'menutype'     => 'mainmenu',
        'base'         => '',
        'startLevel'   => 1,
        'endLevel'     => 0,
        'showAllChildren' => 1,
        'tag_id'       => '',
        'class_sfx'    => '',
        'layout'       => '_:default',
    ]),
], $created, $updated, $failed);

put_module($moduleFactory, $db, 'search', [
    'title'    => 'Carian',
    'module'   => 'mod_finder',
    'position' => 'search',
    'showtitle' => 0,
    'ordering' => 1,
    'params'   => json_encode([
        'searchfilter'  => '',
        'show_autosuggest' => 1,
        'show_advanced' => 0,
        'show_label'    => 0,
        'alt_label'     => 'Cari dalam portal',
        'show_button'   => 1,
        'button_pos'    => 'right',
        'opensearch'    => 1,
        'set_itemid'    => 0,
    ]),
], $created, $updated, $failed);

put_module($moduleFactory, $db, 'breadcrumbs', [
    'title'    => 'Anda di sini',
    'module'   => 'mod_breadcrumbs',
    'position' => 'breadcrumbs',
    'showtitle' => 0,
    'ordering' => 1,
    'params'   => json_encode([
        'showHere'  => 1,
        'homeText'  => 'Laman Utama',
        'showHome'  => 1,
        'showLast'  => 1,
        'separator' => '',
    ]),
], $created, $updated, $failed);

/* ── Homepage bands ──────────────────────────────────────────────────────── */

out('');
out('Homepage');

// Joomla 5.2 merged the old mod_articles_news/latest/category modules into
// mod_articles; the parameter names below are that module's, not the old ones.
$newsParams = fn(int $catId, int $count, string $layout) => json_encode([
    'mode'                      => 'normal',
    'catid'                     => [$catId],
    'show_child_category_articles' => 1,
    'levels'                    => 2,
    'count'                     => $count,
    'show_featured'             => 'show',
    'show_archived'             => 0,
    'article_ordering'          => 'a.created',
    'article_ordering_direction' => 'DESC',
    'article_grouping'          => 'none',
    // The layout interpolates this straight into the tag, so it needs the tag
    // name - a bare 3 renders a literal "<3 class=..." in the output.
    'item_heading'              => 'h3',
    'item_title'                => 1,
    'link_titles'               => 1,
    'show_date'                 => 1,
    'show_date_field'           => 'created',
    'show_date_format'          => 'd M Y',
    'show_introtext'            => 0,
    'show_readmore'             => 1,
    'show_readmore_title'       => 0,
    'layout'                    => $layout,
]);

put_module($moduleFactory, $db, 'pengumuman', array_merge([
    'title'    => 'Pengumuman',
    'module'   => 'mod_articles',
    'position' => 'announcements',
    'ordering' => 1,
    'params'   => $newsParams($catIds['berita/pengumuman'], 6, '_:default'),
], $home), $created, $updated, $failed);

put_module($moduleFactory, $db, 'berita-terkini', array_merge([
    'title'    => 'Berita Terkini',
    'module'   => 'mod_articles',
    'position' => 'announcements',
    'ordering' => 2,
    'params'   => $newsParams($catIds['berita/berita-terkini'], 6, '_:default'),
], $home), $created, $updated, $failed);

put_module($moduleFactory, $db, 'sebut-harga', array_merge([
    'title'    => 'Sebut Harga & Tender',
    'module'   => 'mod_articles',
    'position' => 'announcements',
    'ordering' => 3,
    'params'   => $newsParams($catIds['berita/sebut-harga'], 6, '_:default'),
], $home), $created, $updated, $failed);

put_module($moduleFactory, $db, 'perkhidmatan-online', array_merge([
    'title'    => 'Perkhidmatan Atas Talian',
    'module'   => 'mod_custom',
    'position' => 'services',
    'ordering' => 1,
    'content'  => <<<'HTML'
<div class="lkim-quicktiles">
  <h3>Sistem Online</h3>
  <ul>
    <li><a href="https://eperolehan.gov.my" target="_blank" rel="noopener">ePerolehan</a></li>
    <li><a href="https://www.lkim.gov.my" target="_blank" rel="noopener">e-Lesen Berniaga Ikan</a></li>
    <li><a href="https://www.lkim.gov.my" target="_blank" rel="noopener">e-Diesel / e-Petrol Nelayan</a></li>
  </ul>
  <h3>Warga LKIM</h3>
  <ul>
    <li><a href="https://mail.lkim.gov.my" target="_blank" rel="noopener">E-mel Rasmi</a></li>
    <li><a href="#">Sistem Pengurusan Sumber Manusia</a></li>
  </ul>
  <h3>Aplikasi Mudah Alih</h3>
  <ul>
    <li><a href="#">QFISH</a></li>
    <li><a href="#">MyNelayan</a></li>
  </ul>
</div>
<p class="lkim-note"><em>Pautan sistem perlu disahkan dengan pemilik sistem sebelum go-live.</em></p>
HTML,
    'params' => json_encode(['prepare_content' => 1, 'backgroundimage' => '', 'layout' => '_:default']),
], $home), $created, $updated, $failed);

// "Popular Services" — the quicklinks band on the 2026 homepage. Plain links
// to menu paths that already exist, so nothing here can rot independently.
put_module($moduleFactory, $db, 'perkhidmatan-popular', array_merge([
    'title'    => 'Perkhidmatan Popular',
    'module'   => 'mod_custom',
    'position' => 'quicklinks',
    'ordering' => 1,
    'content'  => <<<'HTML'
<ul class="lk-cardlist">
  <li><a href="/perkhidmatan/kawalselia-pendaratan-ikan-dan-penguatkuasaan"><img src="/images/ikon-perkhidmatan/licensing.png" alt="" width="72" height="72" loading="lazy"><span class="lk-cardlist-title">Pelesenan &amp; Penguatkuasaan</span><span class="lk-cardlist-desc">Pengiktirafan, sijil jeti dan pengawasan kualiti</span></a></li>
  <li><a href="/perkhidmatan/pemasaran-ikan"><img src="/images/ikon-perkhidmatan/industry-info.png" alt="" width="72" height="72" loading="lazy"><span class="lk-cardlist-title">Maklumat Industri</span><span class="lk-cardlist-desc">Harga ikan, pasar nelayan dan CCDC</span></a></li>
  <li><a href="/perkhidmatan/bantuan-kepada-masyarakat-nelayan"><img src="/images/ikon-perkhidmatan/funding-and-programme.png" alt="" width="72" height="72" loading="lazy"><span class="lk-cardlist-title">Bantuan &amp; Pembiayaan</span><span class="lk-cardlist-desc">Skim bantuan, subsidi dan Dana Nelayan</span></a></li>
  <li><a href="/perkhidmatan/pembangunan-infrastruktur"><img src="/images/ikon-perkhidmatan/facilities-and-offices.png" alt="" width="72" height="72" loading="lazy"><span class="lk-cardlist-title">Kemudahan &amp; Pejabat</span><span class="lk-cardlist-desc">Kompleks perikanan, jeti dan bilik sejuk</span></a></li>
  <li><a href="/k-lain-lain/perkhidmatan-dalam-talian"><img src="/images/ikon-perkhidmatan/online-services.png" alt="" width="72" height="72" loading="lazy"><span class="lk-cardlist-title">Perkhidmatan Atas Talian</span><span class="lk-cardlist-desc">e-Dana, e-Pelesenan, W-ICCS dan lain-lain</span></a></li>
</ul>
HTML,
    'params' => json_encode(['prepare_content' => 1, 'layout' => '_:default']),
], $home), $created, $updated, $failed);

// Related agencies strip.
put_module($moduleFactory, $db, 'agensi', array_merge([
    'title'    => 'Agensi Berkaitan',
    'module'   => 'mod_custom',
    'position' => 'agencies',
    'ordering' => 1,
    // The reference design has no logo for the ministry or MARDI — it reuses
    // the MyGov mark for the ministry, which would be wrong on a real portal.
    // Those two are text links until LKIM supplies the artwork.
    'content'  => <<<'HTML'
<ul class="lk-logolist">
  <li><a href="https://www.malaysia.gov.my" target="_blank" rel="noopener"><img src="/images/agensi/mygov.png" alt="MyGOV" loading="lazy"></a></li>
  <li><a href="https://www.dof.gov.my" target="_blank" rel="noopener"><img src="/images/agensi/perikanan.png" alt="Jabatan Perikanan Malaysia" loading="lazy"></a></li>
  <li><a href="https://www.doa.gov.my" target="_blank" rel="noopener"><img src="/images/agensi/pertanian.png" alt="Jabatan Pertanian" loading="lazy"></a></li>
  <li><a href="https://www.dvs.gov.my" target="_blank" rel="noopener"><img src="/images/agensi/veterinar.png" alt="Jabatan Perkhidmatan Veterinar" loading="lazy"></a></li>
  <li><a href="https://www.fama.gov.my" target="_blank" rel="noopener"><img src="/images/agensi/fama.png" alt="FAMA" loading="lazy"></a></li>
  <li><a class="lk-logolist-text" href="https://www.mafs.gov.my" target="_blank" rel="noopener">Kementerian Pertanian dan Keterjaminan Makanan</a></li>
  <li><a class="lk-logolist-text" href="https://www.mardi.gov.my" target="_blank" rel="noopener">MARDI</a></li>
</ul>
HTML,
    'params' => json_encode(['prepare_content' => 1, 'layout' => '_:default']),
], $home), $created, $updated, $failed);

put_module($moduleFactory, $db, 'arkib', array_merge([
    'title'    => 'Arkib',
    'module'   => 'mod_articles_archive',
    'position' => 'highlights',
    'ordering' => 2,
    'params'   => json_encode(['count' => 12, 'layout' => '_:default']),
], $home), $created, $updated, $failed);

put_module($moduleFactory, $db, 'kategori-perkhidmatan', array_merge([
    'title'    => 'Perkhidmatan LKIM',
    'module'   => 'mod_articles_categories',
    'position' => 'highlights',
    'ordering' => 1,
    'params'   => json_encode([
        'parent'         => $catIds['perkhidmatan'],
        'show_description' => 1,
        'show_children'  => 0,
        'count'          => 0,
        'layout'         => '_:default',
    ]),
], $home), $created, $updated, $failed);

/* ── Footer ──────────────────────────────────────────────────────────────── */

out('');
out('Footer');

put_module($moduleFactory, $db, 'footer-alamat', [
    'title'    => 'Hubungi LKIM',
    'module'   => 'mod_custom',
    'position' => 'footer-a',
    'ordering' => 1,
    'content'  => <<<'HTML'
<address>
  Lembaga Kemajuan Ikan Malaysia<br>
  Wisma LKIM, Jalan Desaria, Pulau Meranti<br>
  47120 Puchong, Selangor Darul Ehsan
</address>
<p>
  Tel: 03-8064 4000<br>
  Faks: 03-8064 5471
</p>
HTML,
    'params' => json_encode(['prepare_content' => 1, 'layout' => '_:default']),
], $created, $updated, $failed);

put_module($moduleFactory, $db, 'footer-pautan', [
    'title'    => 'Pautan Pantas',
    'module'   => 'mod_menu',
    'position' => 'footer-b',
    'ordering' => 1,
    'params'   => json_encode([
        'menutype'   => 'footermenu',
        'startLevel' => 1,
        'endLevel'   => 1,
        'showAllChildren' => 0,
        'layout'     => '_:default',
    ]),
], $created, $updated, $failed);

put_module($moduleFactory, $db, 'footer-info', [
    'title'    => 'Info Portal',
    'module'   => 'mod_menu',
    'position' => 'footer-c',
    'ordering' => 1,
    'params'   => json_encode([
        'menutype'   => 'mainmenu',
        'startLevel' => 1,
        'endLevel'   => 1,
        'showAllChildren' => 0,
        'layout'     => '_:default',
    ]),
], $created, $updated, $failed);

put_module($moduleFactory, $db, 'footer-sosial', [
    'title'    => 'Ikuti Kami',
    'module'   => 'mod_custom',
    'position' => 'footer-d',
    'ordering' => 1,
    'content'  => <<<'HTML'
<ul class="lkim-social">
  <li><a href="https://www.facebook.com/lkimmalaysia" target="_blank" rel="noopener">Facebook</a></li>
  <li><a href="https://www.youtube.com/@lkimmalaysia" target="_blank" rel="noopener">YouTube</a></li>
  <li><a href="https://www.instagram.com/lkimmalaysia" target="_blank" rel="noopener">Instagram</a></li>
</ul>
HTML,
    'params' => json_encode(['prepare_content' => 1, 'layout' => '_:default']),
], $created, $updated, $failed);

put_module($moduleFactory, $db, 'syndicate', [
    'title'    => 'RSS',
    'module'   => 'mod_syndicate',
    'position' => 'footer',
    'showtitle' => 0,
    'ordering' => 1,
    'params'   => json_encode(['display_text' => 1, 'text' => 'Suapan RSS', 'format' => 'rss', 'layout' => '_:default']),
], $created, $updated, $failed);

/* ── Retire the stock modules we do not want on a public portal ──────────── */

$db->setQuery(
    $db->getQuery(true)->update($db->quoteName('#__modules'))
        ->set($db->quoteName('published') . ' = 0')
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('module') . ' = ' . $db->quote('mod_login'))
)->execute();

// The stock Main Menu module sits in sidebar-right and duplicates ours.
$db->setQuery(
    $db->getQuery(true)->update($db->quoteName('#__modules'))
        ->set($db->quoteName('published') . ' = 0')
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('position') . ' = ' . $db->quote('sidebar-right'))
        ->where($db->quoteName('note') . ' <> ' . $db->quote('lkim:mainmenu'))
)->execute();

out('');
out('created: ' . $created . '  updated: ' . $updated . '  failed: ' . $failed);
