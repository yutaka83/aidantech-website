<?php
/**
 * Phase 9b: security and performance settings.
 *
 * Run with --production to switch on the settings that would slow development
 * down (page cache, minified assets, HSTS, error suppression). Without it the
 * site stays in the development posture: caching off so edits show up, errors
 * visible.
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

$production = in_array('--production', $argv, true);

out($production ? 'Applying PRODUCTION settings' : 'Applying DEVELOPMENT settings (pass --production to switch)');

/* ── HTTP security headers ───────────────────────────────────────────────── */

out('');
out('HTTP headers');

// The Content-Security-Policy starts in report-only mode. The portal embeds
// YouTube, Facebook and Google Maps, so an enforcing policy has to be tuned
// against real traffic before it is switched on.
// The plugin compares its switches with ===, against integers. Storing "1" as
// a string silently disables them, so the numeric fields stay numeric here.
$headerParams = [
    'xframeoptions'                 => 1,
    'referrerpolicy'                => 'strict-origin-when-cross-origin',
    'coop'                          => 'same-origin',
    'hsts'                          => $production ? 1 : 0,
    'hsts_maxage'                   => 31536000,
    'hsts_subdomains'               => 0,
    'hsts_preload'                  => 0,
    'csp'                           => 1,
    'contentsecuritypolicy'         => 1,
    'contentsecuritypolicy_report_only' => 1,
    'contentsecuritypolicy_client'  => 'site',
    'nonce_enabled'                 => 0,
    'script_hashes_enabled'         => 0,
    'style_hashes_enabled'          => 0,
    'strict_dynamic_enabled'        => 0,
    'frame_ancestors_self_enabled'  => 1,
    'contentsecuritypolicy_values'  => [
        ['directive' => 'default-src', 'value' => "'self'", 'client' => 'site'],
        ['directive' => 'img-src', 'value' => "'self' data: https:", 'client' => 'site'],
        ['directive' => 'frame-src', 'value' => "'self' https://www.youtube.com https://www.youtube-nocookie.com https://www.google.com https://www.facebook.com", 'client' => 'site'],
        ['directive' => 'object-src', 'value' => "'none'", 'client' => 'site'],
        ['directive' => 'base-uri', 'value' => "'self'", 'client' => 'site'],
        ['directive' => 'form-action', 'value' => "'self'", 'client' => 'site'],
    ],
    // X-Content-Type-Options is not in the plugin's allow-list; it comes from
    // the .htaccess block below on the Apache/LiteSpeed production host.
    'additional_httpheader' => [
        ['key' => 'permissions-policy', 'value' => 'geolocation=(), microphone=(), camera=()', 'client' => 'site'],
    ],
];

$db->setQuery(
    $db->getQuery(true)->update($db->quoteName('#__extensions'))
        ->set($db->quoteName('enabled') . ' = 1')
        ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($headerParams)))
        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('httpheaders'))
        ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
)->execute();

out('  System - HTTP Headers enabled');
out('  X-Frame-Options, Referrer-Policy, X-Content-Type-Options, Permissions-Policy');
out('  CSP in REPORT-ONLY mode' . ($production ? '' : ' (HSTS off until production)'));

/* ── Plugins worth having on a public portal ────────────────────────────── */

out('');
out('Plugins');

$plugins = [
    ['sef', 'system', 1],                 // clean URLs in rendered output
    ['redirect', 'system', 1],            // serve the migration redirects
    ['cache', 'system', $production ? 1 : 0],
    ['debug', 'system', 0],
    ['stats', 'system', 0],               // no phoning home from a gov portal
    ['guidedtours', 'system', 0],
    ['webauthn', 'system', 1],            // passkey login for administrators
];

foreach ($plugins as [$element, $folder, $enabled]) {
    $db->setQuery(
        $db->getQuery(true)->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = ' . (int) $enabled)
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($element))
            ->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
    )->execute();

    out(sprintf('  %-14s %s', $element, $enabled ? 'enabled' : 'disabled'));
}

/* ── Global configuration ────────────────────────────────────────────────── */

out('');
out('Global configuration');

$settings = [
    'sef'              => '1',
    'sef_rewrite'      => '1',
    'sef_suffix'       => '0',
    'sef_unique'       => '1',
    'force_ssl'        => $production ? '2' : '0',
    'caching'          => $production ? '2' : '0',
    'cachetime'        => '15',
    'gzip'             => $production ? '1' : '0',
    'debug'            => '0',
    'error_reporting'  => $production ? "'none'" : "'default'",
    'log_deprecated'   => '0',
    'lifetime'         => '30',
    'session_handler'  => "'database'",
    'MetaRights'       => "'Hak cipta terpelihara Lembaga Kemajuan Ikan Malaysia'",
    'robots'           => $production ? "''" : "'noindex, nofollow'",
    'feed_limit'       => '20',
    'access'           => '1',
];

$configFile = JPATH_BASE . '/configuration.php';
$config     = file_get_contents($configFile);

foreach ($settings as $key => $value) {
    $pattern = '/public \$' . $key . '\s*=\s*[^;]*;/';
    $line    = 'public $' . $key . ' = ' . $value . ';';

    $config = preg_match($pattern, $config)
        ? preg_replace($pattern, $line, $config, 1)
        : preg_replace('/(class JConfig\s*\{)/', "$1\n\t" . $line, $config, 1);
}

file_put_contents($configFile, $config);

out('  SEF on, session lifetime 30 min, deprecation logging off');
out('  caching: ' . ($production ? 'conservative (2)' : 'off'));
out('  force_ssl: ' . ($production ? 'entire site' : 'off'));
out('  robots: ' . ($production ? 'indexable' : 'noindex, nofollow'));

/* ── .htaccess ───────────────────────────────────────────────────────────── */

out('');
out('.htaccess');

$htaccess = JPATH_BASE . '/.htaccess';

if (!is_file($htaccess)) {
    copy(JPATH_BASE . '/htaccess.txt', $htaccess);
    out('  created from htaccess.txt');
}

$block = <<<'HTACCESS'

## --- BEGIN LKIM portal hardening (managed by harden.php) ---
# Block direct execution of anything uploaded under images/
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^images/.*\.(php|phtml|php[0-9]|pl|py|cgi|asp|aspx|sh)$ - [F,L,NC]
</IfModule>

# Never serve version-control or dependency metadata
<FilesMatch "(^\.|composer\.(json|lock)|package(-lock)?\.json|\.md$|\.sql$|\.log$)">
    Require all denied
</FilesMatch>

<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always unset X-Powered-By
    Header unset X-Powered-By
</IfModule>
## --- END LKIM portal hardening ---
HTACCESS;

$current = file_get_contents($htaccess);

if (!str_contains($current, 'BEGIN LKIM portal hardening')) {
    file_put_contents($htaccess, $current . $block);
    out('  hardening block appended');
} else {
    out('  hardening block already present');
}

out('');
out('Done.');
