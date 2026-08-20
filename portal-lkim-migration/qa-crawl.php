<?php
/**
 * Phase 9c: crawl every menu item and every article on the local portal and
 * report anything that 404s, errors, or comes back with no real content.
 *
 * Writes reports/qa-crawl.csv.
 */

const _JEXEC = 1;

define('JPATH_BASE', 'C:\\Users\\hiday\\Herd\\portal-lkim');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

require __DIR__ . '/lib.php';

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

const SITE        = 'https://portal-lkim.test';
const CONCURRENCY = 8;

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

$app = $container->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();

$db = $container->get(DatabaseInterface::class);

$sample = (int) (getopt('', ['sample::'])['sample'] ?? 0);

/* ── Collect URLs the same way relink.php derives them ──────────────────── */

$articleRoute  = [];
$categoryRoute = [];
$targets       = [];

foreach ($db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'title', 'path', 'link', 'menutype']))
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('published') . ' = 1')
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
)->loadAssocList() as $item) {
    $targets['menu:' . $item['id']] = ['url' => '/' . $item['path'], 'label' => $item['title'], 'kind' => 'menu/' . $item['menutype']];

    if (preg_match('#view=article&id=(\d+)#', $item['link'], $m)) {
        $articleRoute[(int) $m[1]] = '/' . $item['path'];
    } elseif (preg_match('#view=category.*?[&?]id=(\d+)#', $item['link'], $m)) {
        $categoryRoute[(int) $m[1]] = '/' . $item['path'];
    }
}

$articles = $db->setQuery(
    $db->getQuery(true)->select($db->quoteName(['id', 'title', 'alias', 'catid', 'language']))
        ->from($db->quoteName('#__content'))
        ->where($db->quoteName('state') . ' = 1')
)->loadAssocList();

foreach ($articles as $a) {
    $url = $articleRoute[(int) $a['id']]
        ?? (isset($categoryRoute[(int) $a['catid']]) ? $categoryRoute[(int) $a['catid']] . '/' . $a['alias'] : null);

    if ($url === null) {
        continue;
    }

    if ($a['language'] === 'en-GB') {
        $url = '/en' . $url;
    }

    $targets['article:' . $a['id']] = ['url' => $url, 'label' => $a['title'], 'kind' => 'article'];
}

$targets['home'] = ['url' => '/', 'label' => 'Laman Utama', 'kind' => 'home'];
$targets['home-en'] = ['url' => '/en', 'label' => 'Home (EN)', 'kind' => 'home'];

$queue = array_values($targets);

if ($sample > 0 && count($queue) > $sample) {
    shuffle($queue);
    $queue = array_slice($queue, 0, $sample);
}

out('Crawling ' . count($queue) . ' URLs');

/* ── Crawl ───────────────────────────────────────────────────────────────── */

$rows    = [];
$multi   = curl_multi_init();
$running = [];
$index   = 0;
$done    = 0;
$bad     = 0;

while ($index < count($queue) || $running) {
    while (count($running) < CONCURRENCY && $index < count($queue)) {
        $item = $queue[$index];
        $ch   = curl_init(SITE . $item['url']);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            // Herd's local certificate does not carry this hostname.
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '',
        ]);

        curl_multi_add_handle($multi, $ch);
        $running[(int) $ch] = $item;
        $index++;
    }

    do {
        $status = curl_multi_exec($multi, $active);
    } while ($status === CURLM_CALL_MULTI_PERFORM);

    curl_multi_select($multi, 0.3);

    while ($info = curl_multi_info_read($multi)) {
        $key  = (int) $info['handle'];
        $item = $running[$key];
        $html = (string) curl_multi_getcontent($info['handle']);
        $code = (int) curl_getinfo($info['handle'], CURLINFO_HTTP_CODE);

        curl_multi_remove_handle($multi, $info['handle']);
        curl_close($info['handle']);
        unset($running[$key]);

        // The body between <main> tags is the real page content.
        $mainLength = 0;
        if (preg_match('#<main.*?</main>#s', $html, $m)) {
            $mainLength = mb_strlen(trim(preg_replace('/\s+/u', ' ', strip_tags($m[0]))));
        }

        $errors = [];
        if ($code !== 200) {
            $errors[] = 'http ' . $code;
        }

        // Joomla ships its JS error strings ("A parse error has occurred...")
        // inside a JSON options block, so scan the markup with scripts removed.
        $scanned = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);

        if (preg_match('/(Fatal error:|Parse error:|Warning:\s+\w+\(\)|Uncaught \w*(Error|Exception))/', $scanned, $e)) {
            $errors[] = 'php: ' . trim($e[1]);
        }
        if ($code === 200 && $mainLength < 120) {
            $errors[] = 'thin content (' . $mainLength . ' chars)';
        }

        if ($errors) {
            $bad++;
            $rows[] = [$item['kind'], $item['url'], mb_substr($item['label'], 0, 60), $code, $mainLength, implode('; ', $errors)];
        }

        if (++$done % 200 === 0) {
            out(sprintf('  %4d/%d  problems so far: %d', $done, count($queue), $bad));
        }
    }
}

curl_multi_close($multi);

csv_write(BASE . '/reports/qa-crawl.csv', ['kind', 'url', 'title', 'http', 'main_chars', 'problems'], $rows);

out('');
out('Crawled:  ' . $done);
out('Problems: ' . $bad . ' (reports/qa-crawl.csv)');

$byProblem = [];
foreach ($rows as $r) {
    $tag = preg_replace('/\(.*\)/', '', explode(';', $r[5])[0]);
    $byProblem[trim($tag)] = ($byProblem[trim($tag)] ?? 0) + 1;
}
arsort($byProblem);

foreach ($byProblem as $tag => $n) {
    out(sprintf('  %-28s %d', $tag, $n));
}
