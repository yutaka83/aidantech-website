<?php
/**
 * Phase 8c: load the English content as Falang translations of the imported
 * Malay articles.
 *
 * Falang stores one row per translated field in #__falang_content, keyed by
 * language + reference table + reference id + field name. original_value holds
 * an md5 of the Malay source so Falang can flag a translation as stale when
 * the original changes later.
 *
 * Idempotent: existing English rows for an article are replaced.
 */

const _JEXEC = 1;

define('JPATH_BASE', 'C:\\Users\\hiday\\Herd\\portal-lkim');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

require __DIR__ . '/lib.php';
require __DIR__ . '/Cleaner.php';

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

/* ── Reference data ──────────────────────────────────────────────────────── */

$languageId = (int) $db->setQuery(
    $db->getQuery(true)->select('lang_id')->from($db->quoteName('#__languages'))
        ->where($db->quoteName('lang_code') . ' = ' . $db->quote('en-GB'))
)->loadResult();

if (!$languageId) {
    out('en-GB content language not found - run configure-falang.php first.');
    exit(1);
}

$pairsFile = BASE . '/data/pairs.json';

if (!is_file($pairsFile)) {
    out('data/pairs.json missing - run pair-languages.php first.');
    exit(1);
}

$pairs = json_decode(file_get_contents($pairsFile), true);

// A few Malay pages on the source declare the same English alternate - the
// site's own translation links are wrong there. When several Malay pages claim
// one English page, none of the claims can be trusted, so drop the whole group
// rather than attach an unrelated translation.
$enCounts = array_count_values(array_column($pairs, 'en_wp_id'));
$rejected = [];

$pairs = array_values(array_filter($pairs, function ($p) use ($enCounts, &$rejected) {
    if ($enCounts[$p['en_wp_id']] > 1) {
        $rejected[] = [$p['ms_wp_id'], $p['ms_slug'], $p['en_slug'], 'English page claimed by ' . $enCounts[$p['en_wp_id']] . ' Malay pages'];
        return false;
    }
    return true;
}));

if ($rejected) {
    csv_write(BASE . '/reports/translation-rejected.csv', ['ms_wp_id', 'ms_slug', 'en_slug', 'reason'], $rejected);
    out('Ambiguous pairs rejected: ' . count($rejected) . ' (reports/translation-rejected.csv)');
}

$sources = [];
foreach (['pages', 'posts'] as $set) {
    foreach (jsonl_read(BASE . "/data/$set.jsonl") as $row) {
        $sources[substr($set, 0, -1) . ':' . $row['id']] = $row;
    }
}

$mediaMap = [];
foreach (json_decode(file_get_contents(BASE . '/data/media-plan.json'), true) as $m) {
    $key = strtolower(rawurldecode(preg_split('/[?&#]/', parse_url($m['url'], PHP_URL_PATH))[0]));
    $mediaMap[$key] = $m['path'];
}

$linkMap = is_file(BASE . '/data/link-map.json')
    ? json_decode(file_get_contents(BASE . '/data/link-map.json'), true)
    : [];

$cleaner = new Cleaner($mediaMap, $linkMap);

/* ── Locate the Joomla article behind each Malay source id ──────────────── */

$articles = [];
foreach ($db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'title', 'alias', 'introtext', 'fulltext', 'metadesc', 'metadata']))
        ->from($db->quoteName('#__content'))
)->loadAssocList() as $row) {
    $meta = json_decode($row['metadata'], true);
    if (!empty($meta['wp_id'])) {
        $articles[(int) $meta['wp_id']] = $row;
    }
}

out('Joomla articles with a source id: ' . count($articles));
out('Language pairs to apply:          ' . count($pairs));

// Start from a clean slate so pairs dropped since the last run leave nothing
// behind; every surviving pair is rewritten below.
$db->setQuery(
    $db->getQuery(true)->delete($db->quoteName('#__falang_content'))
        ->where($db->quoteName('language_id') . ' = ' . $languageId)
        ->where($db->quoteName('reference_table') . ' = ' . $db->quote('content'))
)->execute();

/* ── Write translations ──────────────────────────────────────────────────── */

const FIELDS = ['title', 'alias', 'introtext', 'fulltext', 'metadesc'];

$applied  = 0;
$missing  = [];
$rows     = 0;

foreach ($pairs as $pair) {
    $article = $articles[$pair['ms_wp_id']] ?? null;

    if ($article === null) {
        $missing[] = [$pair['ms_wp_id'], $pair['ms_slug'], 'no imported Joomla article'];
        continue;
    }

    $source = $sources[$pair['en_type'] . ':' . $pair['en_wp_id']] ?? null;

    if ($source === null) {
        $missing[] = [$pair['en_wp_id'], $pair['en_slug'], 'English source not harvested'];
        continue;
    }

    $body = $cleaner->clean($source['content']['rendered'] ?? '');

    // Match the split the Malay import used so both languages read alike.
    $intro = $body;
    $full  = '';

    if ($pair['en_type'] === 'post' && strlen($body) >= 900) {
        $pos = stripos($body, '</p>');
        if ($pos !== false) {
            $intro = substr($body, 0, $pos + 4);
            $full  = substr($body, $pos + 4);
        }
    }

    $title = html_entity_decode($source['title']['rendered'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (mb_strlen($title) > 250) {
        $title = rtrim(mb_substr($title, 0, 250)) . '…';
    }

    $values = [
        'title'     => $title !== '' ? $title : $pair['en_slug'],
        'alias'     => mb_substr($pair['en_slug'], 0, 250),
        'introtext' => $intro,
        'fulltext'  => $full,
        'metadesc'  => mb_substr(trim(strip_tags(html_entity_decode($intro))), 0, 240),
    ];

    // Replace any previous English rows for this article.
    $db->setQuery(
        $db->getQuery(true)->delete($db->quoteName('#__falang_content'))
            ->where($db->quoteName('language_id') . ' = ' . $languageId)
            ->where($db->quoteName('reference_table') . ' = ' . $db->quote('content'))
            ->where($db->quoteName('reference_id') . ' = ' . (int) $article['id'])
    )->execute();

    $insert = $db->getQuery(true)->insert($db->quoteName('#__falang_content'))
        ->columns($db->quoteName([
            'language_id', 'reference_id', 'reference_table', 'reference_field',
            'value', 'original_value', 'original_text', 'modified', 'modified_by', 'published',
        ]));

    foreach (FIELDS as $field) {
        $insert->values(implode(',', [
            $languageId,
            (int) $article['id'],
            $db->quote('content'),
            $db->quote($field),
            $db->quote($values[$field]),
            $db->quote(md5((string) $article[$field])),
            $db->quote(''),
            $db->quote(date('Y-m-d H:i:s')),
            0,
            1,
        ]));
        $rows++;
    }

    $db->setQuery($insert)->execute();
    $applied++;

    if ($applied % 100 === 0) {
        out('  ' . $applied . ' articles translated');
    }
}

csv_write(BASE . '/reports/translation-gaps.csv', ['wp_id', 'slug', 'reason'], $missing);

out('');
out('Articles with an English translation: ' . $applied);
out('Falang field rows written:            ' . $rows);
out('Gaps:                                 ' . count($missing) . ' (reports/translation-gaps.csv)');
