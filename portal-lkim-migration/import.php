<?php
/**
 * Phase 4: create the Joomla category tree and import the cleaned articles.
 *
 * Runs inside a booted Joomla console application so the real admin models do
 * the work - nested-set category placement, workflow association, asset rules
 * and alias uniqueness all come for free, which raw INSERTs would get wrong.
 *
 * Idempotent: every created item stores its WordPress id in the article
 * metadata, and a re-run updates rather than duplicates.
 *
 * Usage:
 *   php import.php                 import Malay content (the default tree)
 *   php import.php --lang=en       import unpaired English pages too
 *   php import.php --limit=25      stop after N articles (smoke test)
 *   php import.php --dry-run       report only, write nothing
 */

const _JEXEC = 1;

define('JPATH_BASE', 'C:\\Users\\hiday\\Herd\\portal-lkim');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

require __DIR__ . '/lib.php';
require __DIR__ . '/Cleaner.php';

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;

/* ── Boot Joomla ─────────────────────────────────────────────────────────── */

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

$app = $container->get(\Joomla\Console\Application::class);
Factory::$application = $app;

// The extension namespace map is normally registered inside $app->execute().
// This script drives the models directly, so register it by hand or every
// component class (ArticleModel, CategoriesComponent, ...) fails to autoload.
$app->createExtensionNamespaceMap();

$app->getLanguage()->load('joomla', JPATH_ADMINISTRATOR);

$db = $container->get(DatabaseInterface::class);

// Act as the first Super User so model save() passes its ACL checks.
$adminId = (int) $db->setQuery(
    $db->getQuery(true)
        ->select('u.id')
        ->from($db->quoteName('#__users', 'u'))
        ->join('INNER', $db->quoteName('#__user_usergroup_map', 'm'), 'm.user_id = u.id')
        ->where('m.group_id = 8')
        ->order('u.id ASC'),
    0,
    1
)->loadResult();

$admin = new User($adminId);
$app->loadIdentity($admin);

/* ── Options ─────────────────────────────────────────────────────────────── */

$opts   = getopt('', ['lang::', 'limit::', 'dry-run', 'only::']);
$lang   = $opts['lang'] ?? 'ms';
$limit  = isset($opts['limit']) ? (int) $opts['limit'] : 0;
$dryRun = isset($opts['dry-run']);
$only   = $opts['only'] ?? null;

/* ── Load the resolved plan ──────────────────────────────────────────────── */

$resolved = json_decode(file_get_contents(BASE . '/data/resolved.json'), true);

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

/* ── Categories ──────────────────────────────────────────────────────────── */

$catFactory = $app->bootComponent('com_categories')->getMVCFactory();

/** @var array<string,int> path => joomla category id */
$catIds = [];

function existing_category(DatabaseInterface $db, string $alias, int $parentId): ?int
{
    $query = $db->getQuery(true)
        ->select('id')
        ->from($db->quoteName('#__categories'))
        ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
        ->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
        ->where($db->quoteName('parent_id') . ' = ' . (int) $parentId);

    $id = $db->setQuery($query)->loadResult();

    return $id ? (int) $id : null;
}

out('Categories');

foreach ($resolved['categories'] as $path => [$title, $description]) {
    $parts  = explode('/', $path);
    $alias  = array_pop($parts);
    $parent = $parts ? ($catIds[implode('/', $parts)] ?? 1) : 1;

    $existing = existing_category($db, $alias, $parent);

    if ($existing) {
        $catIds[$path] = $existing;
        out(sprintf('  = %-44s #%d', $path, $existing));
        continue;
    }

    if ($dryRun) {
        $catIds[$path] = -1;
        out(sprintf('  + %-44s (dry run)', $path));
        continue;
    }

    $model = $catFactory->createModel('Category', 'Administrator', ['ignore_request' => true]);
    $ok    = $model->save([
        'id'          => 0,
        'parent_id'   => $parent,
        'extension'   => 'com_content',
        'title'       => $title,
        'alias'       => $alias,
        'description' => $description,
        'published'   => 1,
        'access'      => 1,
        'language'    => '*',
        'params'      => '{}',
        'metadata'    => '{}',
    ]);

    if (!$ok) {
        out('  ! ' . $path . ': ' . $model->getError());
        continue;
    }

    $catIds[$path] = (int) $model->getItem()->id;
    out(sprintf('  + %-44s #%d', $path, $catIds[$path]));
}

/* ── Link map: source slug => Joomla route ───────────────────────────────── */
/* Built after articles exist, so the first pass leaves internal links alone
   and a second pass rewrites them. */

// English pages that ARE translations of a Malay page live in Falang, not as
// separate articles. Only the standalone English pages get imported here.
$translated = [];

if ($lang === 'en' && is_file(BASE . '/data/pairs.json')) {
    foreach (json_decode(file_get_contents(BASE . '/data/pairs.json'), true) as $pair) {
        $translated[$pair['en_wp_id']] = true;
    }

    out('English pages that are translations (skipped here): ' . count($translated));
}

$linkMapFile = BASE . '/data/link-map.json';
$linkMap     = is_file($linkMapFile) ? json_decode(file_get_contents($linkMapFile), true) : [];

$cleaner = new Cleaner($mediaMap, $linkMap);

/* ── Articles ────────────────────────────────────────────────────────────── */

// ArticleModel::loadForm() reads JPATH_COMPONENT, which only the web/admin
// entry points define. Categories are already done at this point, so pinning
// it to com_content is safe.
if (!defined('JPATH_COMPONENT')) {
    define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_content');
    define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_content');
    define('JPATH_COMPONENT_SITE', JPATH_SITE . '/components/com_content');
}

$articleFactory = $app->bootComponent('com_content')->getMVCFactory();

function existing_article(DatabaseInterface $db, int $wpId): ?int
{
    $query = $db->getQuery(true)
        ->select('id')
        ->from($db->quoteName('#__content'))
        ->where($db->quoteName('metadata') . ' LIKE ' . $db->quote('%"wp_id":"' . $wpId . '"%'));

    $id = $db->setQuery($query)->loadResult();

    return $id ? (int) $id : null;
}

/** Split a body into intro + full text at the first paragraph break. */
function split_body(string $html, bool $split): array
{
    if (!$split) {
        return [$html, ''];
    }

    $pos = stripos($html, '</p>');

    if ($pos === false || strlen($html) < 900) {
        return [$html, ''];
    }

    return [substr($html, 0, $pos + 4), substr($html, $pos + 4)];
}

/** Drop a leading heading that just repeats the article title. */
function drop_duplicate_heading(string $html, string $title): string
{
    if (!preg_match('#^\s*<(h[1-6])>(.*?)</\1>#is', $html, $m)) {
        return $html;
    }

    $norm = fn($s) => preg_replace('/[^a-z0-9]+/', '', mb_strtolower(strip_tags(html_entity_decode($s))));

    if ($norm($m[2]) !== '' && $norm($m[2]) === $norm($title)) {
        return ltrim(substr($html, strlen($m[0])));
    }

    return $html;
}

$stats       = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
$unresolved  = [];
$routeMap    = [];
$summaryRows = [];
$n           = 0;

out('');
out('Articles');

foreach ($resolved['articles'] as $a) {
    if ($a['lang'] !== $lang) {
        continue;
    }

    if ($only !== null && $a['slug'] !== $only) {
        continue;
    }

    if (isset($translated[$a['wp_id']])) {
        $stats['skipped']++;
        continue;
    }

    if ($limit && $n >= $limit) {
        break;
    }

    $source = $sources[$a['type'] . ':' . $a['wp_id']] ?? null;

    if (!$source) {
        $stats['skipped']++;
        continue;
    }

    $n++;

    $catId = $catIds[$a['category']] ?? $catIds['lain-lain'];
    $body  = $cleaner->clean($source['content']['rendered'] ?? '');
    $body  = drop_duplicate_heading($body, $a['title']);

    foreach ($cleaner->unresolvedLinks as $u) {
        $unresolved[] = [$a['type'], $a['wp_id'], $a['slug'], $u];
    }

    [$intro, $full] = split_body($body, $a['type'] === 'post');

    $existingId = existing_article($db, $a['wp_id']);

    // Quotation and tender titles routinely run past 300 characters; #__content
    // .title is varchar(255), so trim at a word boundary and keep the full text
    // at the top of the body so nothing is actually lost.
    $fullTitle = $a['title'] !== '' ? $a['title'] : $a['slug'];
    $title     = $fullTitle;

    if (mb_strlen($title) > 250) {
        $title = mb_substr($title, 0, 250);
        $title = rtrim(mb_substr($title, 0, mb_strrpos($title, ' ') ?: 250)) . '…';
        $intro = '<p><strong>' . htmlspecialchars($fullTitle, ENT_QUOTES, 'UTF-8') . '</strong></p>' . $intro;
    }

    $data = [
        'id'        => $existingId ?: 0,
        'title'     => $title,
        'alias'     => mb_substr($a['slug'], 0, 250),
        'catid'     => $catId,
        'introtext' => $intro,
        'fulltext'  => $full,
        'state'     => $a['status'] === 'publish' ? 1 : 0,
        'access'    => 1,
        // Malay articles are the canonical records and stay language-neutral so
        // Falang can translate them; standalone English pages are tagged en-GB
        // and therefore only surface under /en/.
        'language'  => $lang === 'en' ? 'en-GB' : '*',
        'created'   => date('Y-m-d H:i:s', strtotime($a['created'])),
        'modified'  => date('Y-m-d H:i:s', strtotime($a['modified'])),
        'created_by' => $GLOBALS['adminId'] ?? 0,
        'metakey'   => '',
        'metadesc'  => mb_substr(trim(strip_tags(html_entity_decode($intro))), 0, 240),
        'metadata'  => json_encode([
            'robots'    => '',
            'author'    => '',
            'rights'    => '',
            'wp_id'     => (string) $a['wp_id'],
            'wp_type'   => $a['type'],
            'wp_link'   => $a['link'],
            'wp_lang'   => $a['lang'],
        ]),
        'images'    => '{}',
        'urls'      => '{}',
        'attribs'   => '{}',
    ];

    if ($dryRun) {
        out(sprintf('  ~ %-58s %s', mb_substr($a['slug'], 0, 58), $a['category']));
        continue;
    }

    $model = $articleFactory->createModel('Article', 'Administrator', ['ignore_request' => true]);

    if (!$model->save($data)) {
        // The source reuses one slug for both languages, so a standalone
        // English page can collide with its Malay namesake in the same
        // category. Retry once with a language suffix.
        $collision = str_contains((string) $model->getError(), 'UNIQUE_ALIAS');

        if ($collision && $lang === 'en') {
            $data['alias'] = mb_substr($a['slug'], 0, 246) . '-en';
            $model = $articleFactory->createModel('Article', 'Administrator', ['ignore_request' => true]);
        }

        if (!$collision || $lang !== 'en' || !$model->save($data)) {
            $stats['failed']++;
            out('  ! ' . $a['slug'] . ': ' . $model->getError());
            continue;
        }
    }

    $saved = $model->getItem();
    $stats[$existingId ? 'updated' : 'created']++;

    $routeMap[$a['slug']] = [
        'id'    => (int) $saved->id,
        'alias' => $saved->alias,
        'catid' => (int) $saved->catid,
    ];

    $summaryRows[] = [$a['type'], $a['wp_id'], $a['slug'], $a['category'], (int) $saved->id, strlen($body)];

    if ($n % 50 === 0) {
        out(sprintf('  %4d  created=%d updated=%d failed=%d', $n, $stats['created'], $stats['updated'], $stats['failed']));
    }
}

/* ── Reports ─────────────────────────────────────────────────────────────── */

if (!$dryRun) {
    $existingRoutes = is_file(BASE . '/data/route-map.json')
        ? json_decode(file_get_contents(BASE . '/data/route-map.json'), true)
        : [];

    file_put_contents(
        BASE . '/data/route-map.json',
        json_encode(array_merge($existingRoutes, $routeMap), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    csv_write(BASE . '/reports/import-unresolved-links-' . $lang . '.csv', ['type', 'wp_id', 'slug', 'url'], $unresolved);
    csv_write(BASE . '/reports/migration-summary-' . $lang . '.csv', ['type', 'wp_id', 'slug', 'category', 'joomla_id', 'body_bytes'], $summaryRows);
}

out('');
out('Processed: ' . $n);
out('  created: ' . $stats['created']);
out('  updated: ' . $stats['updated']);
out('  failed:  ' . $stats['failed']);
out('  no source: ' . $stats['skipped']);
out('  unresolved internal links: ' . count($unresolved));
