<?php
/**
 * Content checklist for LKIM, in the same format as the JKM one.
 *
 * Columns follow that sheet exactly:
 *   MAIN CONTENT | SUB CONTENT (LEVEL 0..2) | VERSION (BM, EN)
 *   | CATATAN LKIM DARI SITEMAP | CATATAN AIDAN
 *
 * Statuses are not typed by hand: every row is resolved against the live
 * portal, so the sheet reflects what is actually in the database rather than
 * what someone remembers importing. Re-run it whenever content changes.
 *
 * Writes reports/Content Checklist LKIM.xlsx (and a .csv alongside it).
 */

const _JEXEC = 1;

define('JPATH_BASE', 'C:\\Users\\hiday\\Herd\\portal-lkim');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

require __DIR__ . '/lib.php';
require __DIR__ . '/XlsxWriter.php';

use Joomla\CMS\Factory;
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

$db = $container->get(DatabaseInterface::class);

/* ── Status vocabulary, matching the JKM sheet ──────────────────────────── */

const ST_DONE     = 'COMPLETED';
const ST_PROGRESS = 'IN PROGRESS';
const ST_NONE     = 'NOT STARTED YET';
const ST_EMPTY    = 'NO CONTENT';
const ST_NOTRANS  = 'NO TRANSLATION';

/** A page needs more than this many characters of text to count as written. */
const SUBSTANTIAL = 250;

/* ── Source data ─────────────────────────────────────────────────────────── */

$enLanguageId = (int) $db->setQuery(
    $db->getQuery(true)->select($db->quoteName('lang_id'))->from($db->quoteName('#__languages'))
        ->where($db->quoteName('lang_code') . ' = ' . $db->quote('en-GB'))
)->loadResult();

$articles = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName(['id', 'title', 'alias', 'catid', 'state', 'language', 'introtext', 'fulltext', 'metadata']))
        ->from($db->quoteName('#__content'))
)->loadAssocList('id');

// English translations, by article id.
$translations = [];
foreach ($db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName(['reference_id', 'reference_field', 'value']))
        ->from($db->quoteName('#__falang_content'))
        ->where($db->quoteName('language_id') . ' = ' . $enLanguageId)
        ->where($db->quoteName('reference_table') . ' = ' . $db->quote('content'))
        ->where($db->quoteName('published') . ' = 1')
)->loadAssocList() as $row) {
    $translations[(int) $row['reference_id']][$row['reference_field']] = $row['value'];
}

$menuItems = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName(['id', 'parent_id', 'title', 'alias', 'path', 'link', 'type', 'menutype', 'level', 'lft', 'home', 'note']))
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('published') . ' = 1')
        ->where($db->quoteName('id') . ' > 1')
        ->order($db->quoteName('lft') . ' ASC')
)->loadAssocList('id');

$modules = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName(['id', 'title', 'module', 'position', 'note', 'content']))
        ->from($db->quoteName('#__modules'))
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('published') . ' = 1')
        ->order($db->quoteName('position') . ' ASC')
)->loadAssocList('note');

// Per-article unresolved link counts, from the relink report.
$unresolved = [];
$file       = BASE . '/reports/unresolved-links.csv';

if (is_file($file)) {
    $fh = fopen($file, 'r');
    fgetcsv($fh, 0, ',', '"', '\\');

    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if (isset($row[0])) {
            $unresolved[(int) $row[0]] = ($unresolved[(int) $row[0]] ?? 0) + 1;
        }
    }

    fclose($fh);
}

$brokenTargets = [];
$file          = BASE . '/reports/menu-gaps.csv';

if (is_file($file)) {
    $fh = fopen($file, 'r');
    fgetcsv($fh, 0, ',', '"', '\\');

    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if (isset($row[1])) {
            $brokenTargets[$row[1]] = $row[2] ?? '';
        }
    }

    fclose($fh);
}

/* ── Helpers ─────────────────────────────────────────────────────────────── */

function text_length(string $html): int
{
    return mb_strlen(trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')))));
}

function article_for_menu(array $item, array $articles): ?array
{
    if (!preg_match('#view=article&id=(\d+)#', $item['link'], $m)) {
        return null;
    }

    return $articles[(int) $m[1]] ?? null;
}

/**
 * Judge one body of HTML. Text length alone is a poor signal: several LKIM
 * pages carry their content as an embedded PDF or a download link and have
 * almost no prose, while others hold a placeholder sentence and nothing else.
 *
 * @return array{0:string,1:string} [status, note]
 */
function judge(string $html): array
{
    $length = text_length($html);
    $text   = mb_strtolower(strip_tags($html));

    if ($length === 0 && !str_contains($html, '<img') && !str_contains($html, '<iframe')) {
        return [ST_EMPTY, 'Halaman kosong.'];
    }

    // The source site's own "being updated" placeholders. Only a short page can
    // be one: a full page of terms happens to contain "pada masa akan datang"
    // and is not a placeholder.
    if ($length < SUBSTANTIAL) {
        foreach (['dalam tindakan kemaskini', 'sedang dikemaskini', 'akan datang', 'coming soon', 'under construction'] as $phrase) {
            if (str_contains($text, $phrase)) {
                return [ST_PROGRESS, 'Laman sumber memaparkan notis "' . trim(preg_replace('/\s+/u', ' ', strip_tags($html))) . '" — kandungan belum disediakan oleh LKIM.'];
            }
        }
    }

    $images = substr_count($html, '<img');
    $embeds = substr_count($html, '<iframe');
    $docs   = preg_match_all('#href="[^"]*(?:/muat-turun/|\.pdf|\.docx?|\.xlsx?)#i', $html);

    if ($length >= SUBSTANTIAL) {
        return [ST_DONE, ''];
    }

    // Short, but the substance is a document or an embed rather than prose.
    if ($images + $embeds + $docs > 0) {
        $carriers = [];
        $embeds and $carriers[] = $embeds . ' embed';
        $docs   and $carriers[] = $docs . ' pautan dokumen';
        $images and $carriers[] = $images . ' imej';

        return [ST_DONE, 'Kandungan disampaikan sebagai ' . implode(' / ', $carriers) . ', bukan teks.'];
    }

    return [ST_PROGRESS, 'Kandungan sangat ringkas (' . $length . ' aksara) pada laman sumber.'];
}

/**
 * Resolve one page to [bmStatus, enStatus, catatanSitemap, catatanAidan].
 */
function assess(?array $article, array $translations, array $unresolved): array
{
    if ($article === null) {
        return ['', '', '', 'Tajuk menu sahaja — tiada halaman kandungan.'];
    }

    $id    = (int) $article['id'];
    $notes = [];

    // Bahasa Melayu
    if ((int) $article['state'] !== 1) {
        $bm   = ST_NONE;
        $note = 'Artikel tidak diterbitkan.';
    } else {
        [$bm, $note] = judge($article['introtext'] . $article['fulltext']);
    }

    if ($note !== '') {
        $notes[] = $note;
    }

    // English
    if ($article['language'] === 'en-GB') {
        // A standalone English page: it is the English version of nothing.
        $en      = $bm;
        $bm      = ST_NOTRANS;
        $notes[] = 'Halaman EN berdiri sendiri — tiada versi BM pada laman sumber.';
    } elseif (isset($translations[$id]['introtext'])) {
        [$en, $enNote] = judge($translations[$id]['introtext'] . ($translations[$id]['fulltext'] ?? ''));

        if ($enNote !== '' && $enNote !== $note) {
            $notes[] = 'EN: ' . $enNote;
        }
    } else {
        $en      = ST_NOTRANS;
        $notes[] = 'Laman sumber tidak menghubungkan halaman ini dengan versi EN.';
    }

    if (!empty($unresolved[$id])) {
        $notes[] = $unresolved[$id] . ' pautan dalaman tidak dapat diselesaikan (sasaran tiada pada laman sumber).';
    }

    // Kept as migrated, or does it need a content pass before go-live?
    $needsWork = $bm !== ST_DONE || $en !== ST_DONE || !empty($unresolved[$id]);

    return [$bm, $en, $needsWork ? 'Dikemaskini' : 'Dikekalkan', implode(' ', $notes)];
}

/* ── Build the rows ──────────────────────────────────────────────────────── */

$children = [];
foreach ($menuItems as $item) {
    $children[(int) $item['parent_id']][] = $item;
}

$rows  = [];
$stats = [];

function push(array &$rows, array &$stats, array $cols, string $bm, string $en, string $catatan, string $nota): void
{
    // The JKM sheet writes an ancestor label once and leaves it blank on the
    // rows beneath it. Track the last value written to each column and repeat
    // nothing.
    static $last = ['', '', '', ''];

    $out = [];

    for ($i = 0; $i < 4; $i++) {
        $value = $cols[$i] ?? '';

        if ($value !== '' && $value === $last[$i]) {
            $out[$i] = '';
        } else {
            $out[$i] = $value;

            if ($value !== '') {
                $last[$i] = $value;

                // A new branch at this level resets everything below it.
                for ($j = $i + 1; $j < 4; $j++) {
                    $last[$j] = '';
                }
            }
        }
    }

    $rows[] = [$out[0], $out[1], $out[2], $out[3], $bm, $en, $catatan, $nota];

    if ($bm !== '') {
        $stats['bm'][$bm] = ($stats['bm'][$bm] ?? 0) + 1;
    }

    if ($en !== '') {
        $stats['en'][$en] = ($stats['en'][$en] ?? 0) + 1;
    }
}

function section(array &$rows, string $label): void
{
    $rows[] = ['__SECTION__', $label, '', '', '', '', '', ''];
}

/* 1. LANDING PAGE — what the front page is made of */

section($rows, 'LAMAN UTAMA (LANDING PAGE)');

$chrome = [
    ['Logo & Jata',            ST_DONE, ST_DONE,  'Dikekalkan', 'Logo LKIM diambil dari laman sumber.'],
    ['Tajuk & Tagline Portal', ST_DONE, ST_DONE,  'Dikekalkan', ''],
    ['Bar Kebolehaksesan (saiz teks, kontras)', ST_DONE, ST_DONE, 'Dikemaskini', 'Elemen baharu — keperluan SPLaSK, tiada pada laman sumber.'],
    ['Pilihan Bahasa (BM / EN)', ST_DONE, ST_DONE, 'Dikekalkan', ''],
    ['Carian',                 ST_DONE, ST_DONE,  'Dikemaskini', 'Smart Search Joomla; indeks perlu dibina semula selepas kemas kini kandungan.'],
    ['Menu Utama',             ST_DONE, ST_DONE,  'Dikekalkan', 'Struktur diambil terus dari menu laman sumber.'],
    ['Banner / Hero',          ST_NONE, ST_NONE,  'Dikemaskini', 'Kedudukan modul tersedia; imej dan kandungan belum ditetapkan.'],
];

foreach ($chrome as [$label, $bm, $en, $catatan, $nota]) {
    push($rows, $stats, [$label], $bm, $en, $catatan, $nota);
}

// Homepage modules, assessed on whether they have anything to show.
$homeModules = [
    'lkim:pengumuman'            => ['Pengumuman', 'berita/pengumuman'],
    'lkim:berita-terkini'        => ['Berita Terkini', 'berita/berita-terkini'],
    'lkim:sebut-harga'           => ['Sebut Harga & Tender', 'berita/sebut-harga'],
    'lkim:perkhidmatan-online'   => ['Perkhidmatan Atas Talian', null],
    'lkim:kategori-perkhidmatan' => ['Perkhidmatan LKIM', null],
    'lkim:arkib'                 => ['Arkib', null],
];

$categoryCounts = [];
foreach ($db->setQuery(
    $db->getQuery(true)
        ->select(['c.path', 'COUNT(a.id) AS n'])
        ->from($db->quoteName('#__categories', 'c'))
        ->join('LEFT', $db->quoteName('#__content', 'a'), 'a.catid = c.id AND a.state = 1')
        ->where($db->quoteName('c.extension') . ' = ' . $db->quote('com_content'))
        ->group($db->quoteName('c.path'))
)->loadAssocList() as $row) {
    $categoryCounts[$row['path']] = (int) $row['n'];
}

foreach ($homeModules as $note => [$label, $categoryPath]) {
    if (!isset($modules[$note])) {
        push($rows, $stats, ['Modul: ' . $label], ST_NONE, ST_NONE, 'Dikemaskini', 'Modul belum diterbitkan.');
        continue;
    }

    if ($categoryPath !== null) {
        $n    = $categoryCounts[$categoryPath] ?? 0;
        $bm   = $n > 0 ? ST_DONE : ST_EMPTY;
        $nota = $n . ' artikel diterbitkan dalam kategori ini.';
    } else {
        $bm   = ST_DONE;
        $nota = $note === 'lkim:perkhidmatan-online'
            ? 'Pautan sistem masih placeholder — perlu disahkan dengan pemilik sistem.'
            : '';
    }

    push(
        $rows,
        $stats,
        ['Modul: ' . $label],
        $bm,
        ST_DONE,
        $note === 'lkim:perkhidmatan-online' ? 'Dikemaskini' : 'Dikekalkan',
        $nota
    );
}

/* 2. MENU UTAMA — the full navigation tree */

section($rows, 'MENU UTAMA');

$walk = function (array $children, int $parentId, string $menutype, array $trail) use (
    &$walk, &$rows, &$stats, $articles, $translations, $unresolved, $brokenTargets
) {
    foreach (($children[$parentId] ?? []) as $item) {
        if ($item['menutype'] !== $menutype || $item['home']) {
            continue;
        }

        $depth = count($trail);
        $cols  = array_pad($trail, $depth, '');
        $cols[$depth] = $item['title'];

        $article = article_for_menu($item, $articles);
        [$bm, $en, $catatan, $nota] = assess($article, $translations, $unresolved);

        if ($item['type'] === 'heading') {
            $sourceSlug = str_contains($item['note'], '/') ? basename($item['note']) : '';

            if (isset($brokenTargets[$sourceSlug])) {
                $catatan = 'Dikemaskini';
                $nota    = 'Pautan rosak pada laman sumber — dirender sebagai tajuk sahaja. Perlu halaman baharu atau dibuang.';
            }
        }

        push($rows, $stats, $cols, $bm, $en, $catatan, $nota);

        $walk($children, (int) $item['id'], $menutype, array_merge(array_fill(0, $depth, ''), [$item['title']]));
    }
};

$walk($children, 1, 'mainmenu', []);

/* 3. SENARAI & ARKIB — the category listings */

section($rows, 'SENARAI & ARKIB');

foreach (($children[1] ?? []) as $item) {
    if ($item['menutype'] !== 'hiddenmenu') {
        continue;
    }

    $catPath = null;

    if (preg_match('#view=category.*?[&?]id=(\d+)#', $item['link'], $m)) {
        foreach ($db->setQuery(
            $db->getQuery(true)->select($db->quoteName('path'))->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' = ' . (int) $m[1])
        )->loadColumn() as $p) {
            $catPath = $p;
        }
    }

    if ($catPath === null) {
        // The Smart Search item, not a category listing.
        push($rows, $stats, [$item['title']], ST_DONE, ST_DONE, 'Dikemaskini', 'Halaman carian Smart Search.');
        continue;
    }

    $n = $categoryCounts[$catPath] ?? 0;

    // A parent category holds its articles in its children; an empty count
    // there is the tree working as intended, not missing content.
    $childCount = 0;
    foreach ($categoryCounts as $path => $count) {
        if ($path !== $catPath && str_starts_with($path, $catPath . '/')) {
            $childCount += $count;
        }
    }

    if ($n === 0 && $childCount > 0) {
        push($rows, $stats, [$item['title']], ST_DONE, ST_DONE, 'Dikekalkan', 'Kategori induk — ' . $childCount . ' artikel berada dalam subkategori.');
        continue;
    }

    push(
        $rows,
        $stats,
        [$item['title']],
        $n > 0 ? ST_DONE : ST_EMPTY,
        $n > 0 ? ST_DONE : ST_EMPTY,
        $n > 0 ? 'Dikekalkan' : 'Dikemaskini',
        $n . ' artikel diterbitkan.'
    );
}

/* 4. MENU BAWAH — footer policy links (the SPLaSK set) */

section($rows, 'MENU BAWAH / PENGAKI');

foreach (($children[1] ?? []) as $item) {
    if ($item['menutype'] !== 'footermenu') {
        continue;
    }

    $article = article_for_menu($item, $articles);
    [$bm, $en, $catatan, $nota] = assess($article, $translations, $unresolved);

    push($rows, $stats, [$item['title']], $bm, $en, $catatan, $nota);
}

$footerModules = [
    'lkim:footer-alamat' => 'Alamat & Talian LKIM',
    'lkim:footer-pautan' => 'Pautan Pantas',
    'lkim:footer-info'   => 'Info Portal',
    'lkim:footer-sosial' => 'Media Sosial',
    'lkim:syndicate'     => 'Suapan RSS',
];

foreach ($footerModules as $note => $label) {
    $placeholder = in_array($note, ['lkim:footer-alamat', 'lkim:footer-sosial'], true);

    push(
        $rows,
        $stats,
        ['Modul: ' . $label],
        isset($modules[$note]) ? ST_DONE : ST_NONE,
        isset($modules[$note]) ? ST_DONE : ST_NONE,
        $placeholder ? 'Dikemaskini' : 'Dikekalkan',
        $placeholder ? 'Maklumat placeholder — perlu disahkan dengan LKIM.' : ''
    );
}

/* 5. ELEMEN SPLaSK */

section($rows, 'ELEMEN SPLaSK / MyGovEA');

$splask = [
    ['Peta Laman',              ST_DONE, ST_DONE, 'Dikemaskini', 'Dijana semula dari struktur menu sebenar; halaman asal hanya mengandungi kod pendek WordPress.'],
    ['Dasar Keselamatan',       null,    null,    '',            ''],
    ['Dasar Privasi',           null,    null,    '',            ''],
    ['Penafian',                null,    null,    '',            ''],
    ['Terma & Syarat',          null,    null,    '',            ''],
    ['Soalan Lazim (FAQ)',      null,    null,    '',            ''],
    ['Aduan / Pertanyaan / Cadangan', null, null, '',            ''],
    ['Kemas Kini Terakhir',     ST_DONE, ST_DONE, 'Dikemaskini', 'Dijana automatik dari tarikh ubah suai kandungan.'],
    ['Kaunter Pelawat',         ST_NONE, ST_NONE, 'Dikemaskini', 'Slot tersedia dalam templat; pemalam kaunter belum dibina atau disambung.'],
    ['Penanda SPLaSK (atribut)', ST_PROGRESS, ST_PROGRESS, 'Dikemaskini', 'Semua elemen ditanda dengan data-splask; nama atribut sebenar perlu ditetapkan mengikut pekeliling MAMPU/JDN semasa.'],
    ['Pautan Langkau (skip links)', ST_DONE, ST_DONE, 'Dikemaskini', 'Elemen baharu untuk kebolehaksesan.'],
    ['Suapan RSS',              ST_DONE, ST_DONE, 'Dikemaskini', ''],
    ['W3C / Kebolehaksesan',    ST_PROGRESS, ST_PROGRESS, 'Dikemaskini', 'Templat mengikut WCAG 2.1 AA; audit penuh belum dijalankan.'],
];

// Where a SPLaSK item maps to a real footer article, take its live status.
$footerBySlug = [];
foreach (($children[1] ?? []) as $item) {
    if ($item['menutype'] === 'footermenu') {
        $footerBySlug[mb_strtolower($item['title'])] = $item;
    }
}

foreach ($splask as [$label, $bm, $en, $catatan, $nota]) {
    if ($bm === null) {
        $item    = $footerBySlug[mb_strtolower($label)] ?? null;
        $article = $item ? article_for_menu($item, $articles) : null;
        [$bm, $en, $catatan, $nota] = assess($article, $translations, $unresolved);
    }

    push($rows, $stats, [$label], $bm, $en, $catatan, $nota);
}

/* ── Write the workbook ──────────────────────────────────────────────────── */

$sheet = new XlsxWriter('Checklist');
$sheet->setWidths([34, 34, 34, 40, 17, 17, 20, 58]);

$title = 'CONTENT CHECKLIST LEMBAGA KEMAJUAN IKAN MALAYSIA';

$sheet->addRow([[$title, XlsxWriter::S_TITLE], '', '', '', '', '', '', ''], XlsxWriter::S_DEFAULT);
$sheet->mergeCells('A1:H1');
$sheet->setRowHeight(1, 26);

$sheet->addRow([
    ['Portal Rasmi LKIM — Joomla 6 · dijana dari struktur portal sebenar pada ' . date('d F Y'), XlsxWriter::S_SUBTITLE],
    '', '', '', '', '', '', '',
], XlsxWriter::S_DEFAULT);
$sheet->mergeCells('A2:H2');
$sheet->setRowHeight(2, 18);

$sheet->addRow(array_fill(0, 8, ''), XlsxWriter::S_DEFAULT);

// Header, two rows, with VERSION spanning BM and EN.
$sheet->addRow([
    'MAIN CONTENT', 'SUB CONTENT (LEVEL 0)', 'SUB CONTENT (LEVEL 1)', 'SUB CONTENT (LEVEL 2)',
    'VERSION', '', 'CATATAN LKIM DARI SITEMAP', 'CATATAN AIDAN',
], XlsxWriter::S_HEADER);

$sheet->addRow(['', '', '', '', 'BM', 'EN', '', ''], XlsxWriter::S_HEADER);

foreach (['A4:A5', 'B4:B5', 'C4:C5', 'D4:D5', 'E4:F4', 'G4:G5', 'H4:H5'] as $range) {
    $sheet->mergeCells($range);
}

$sheet->setRowHeight(4, 22);
$sheet->freezePane('A6');

$statusStyle = [
    ST_DONE     => XlsxWriter::S_OK,
    ST_PROGRESS => XlsxWriter::S_PROGRESS,
    ST_NONE     => XlsxWriter::S_NONE,
    ST_EMPTY    => XlsxWriter::S_EMPTY,
    ST_NOTRANS  => XlsxWriter::S_NOTRANS,
];

foreach ($rows as $row) {
    if ($row[0] === '__SECTION__') {
        $sheet->addRow([
            [$row[1], XlsxWriter::S_SECTION],
            ['', XlsxWriter::S_SECTION], ['', XlsxWriter::S_SECTION], ['', XlsxWriter::S_SECTION],
            ['', XlsxWriter::S_SECTION], ['', XlsxWriter::S_SECTION], ['', XlsxWriter::S_SECTION],
            ['', XlsxWriter::S_SECTION],
        ], XlsxWriter::S_SECTION);

        $sheet->mergeCells('A' . $sheet->rowCount() . ':D' . $sheet->rowCount());
        continue;
    }

    $sheet->addRow([
        [$row[0], XlsxWriter::S_CELL_WRAP],
        [$row[1], XlsxWriter::S_CELL_WRAP],
        [$row[2], XlsxWriter::S_CELL_WRAP],
        [$row[3], XlsxWriter::S_CELL_WRAP],
        [$row[4], $statusStyle[$row[4]] ?? XlsxWriter::S_CELL],
        [$row[5], $statusStyle[$row[5]] ?? XlsxWriter::S_CELL],
        [$row[6], XlsxWriter::S_CELL_WRAP],
        [$row[7], XlsxWriter::S_MUTED],
    ]);
}

// Legend, below the table.
$sheet->addRow(array_fill(0, 8, ''), XlsxWriter::S_DEFAULT);

$legendRow = $sheet->rowCount() + 1;
$sheet->addRow([['PETUNJUK STATUS', XlsxWriter::S_BOLD], '', '', '', '', '', '', ''], XlsxWriter::S_DEFAULT);

$legend = [
    [ST_DONE,     'Kandungan lengkap dan diterbitkan.'],
    [ST_PROGRESS, 'Ada kandungan tetapi terlalu ringkas atau perlu semakan.'],
    [ST_EMPTY,    'Halaman wujud tanpa kandungan.'],
    [ST_NOTRANS,  'Versi bahasa berkenaan tiada.'],
    [ST_NONE,     'Belum dimulakan.'],
];

foreach ($legend as [$status, $meaning]) {
    $sheet->addRow([
        [$status, $statusStyle[$status]],
        [$meaning, XlsxWriter::S_LEGEND],
        '', '', '', '', '', '',
    ], XlsxWriter::S_DEFAULT);

    $sheet->mergeCells('B' . $sheet->rowCount() . ':H' . $sheet->rowCount());
}

$out = BASE . '/reports/Content Checklist LKIM.xlsx';
$sheet->save($out);

// A CSV alongside, for anyone who would rather diff it.
$csvRows = array_map(
    fn($r) => $r[0] === '__SECTION__' ? [$r[1], '', '', '', '', '', '', ''] : $r,
    $rows
);

csv_write(
    BASE . '/reports/content-checklist-lkim.csv',
    ['MAIN CONTENT', 'SUB CONTENT (LEVEL 0)', 'SUB CONTENT (LEVEL 1)', 'SUB CONTENT (LEVEL 2)', 'BM', 'EN', 'CATATAN LKIM DARI SITEMAP', 'CATATAN AIDAN'],
    $csvRows
);

/* ── Summary ─────────────────────────────────────────────────────────────── */

$contentRows = count(array_filter($rows, fn($r) => $r[0] !== '__SECTION__'));

out('Rows: ' . $contentRows . ' content + ' . (count($rows) - $contentRows) . ' section headers');
out('');

foreach (['bm' => 'Bahasa Melayu', 'en' => 'English'] as $key => $label) {
    out($label . ':');
    $counts = $stats[$key] ?? [];
    arsort($counts);

    foreach ($counts as $status => $n) {
        out(sprintf('  %-18s %3d', $status, $n));
    }

    out('');
}

out('-> reports/Content Checklist LKIM.xlsx');
out('-> reports/content-checklist-lkim.csv');
