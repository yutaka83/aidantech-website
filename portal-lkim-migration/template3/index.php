<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.lkim3
 *
 * Portal Rasmi Lembaga Kemajuan Ikan Malaysia — LKIM-3 design.
 *
 * Chrome is shared with tpl_lkim and tpl_lkim2 (government identification bar,
 * accessibility controls, SPLaSK markers) but the shell is different: a floating
 * white header card with a mega menu sits on top of a full-bleed hero photo, and
 * the audience gateway cards overlap the bottom of that hero.
 *
 * Homepage bands split the same two ways as the earlier templates. Anything with
 * a natural Joomla source — services, gallery, news, agencies, the footer — is a
 * module position, and the template falls back to the design's own sample
 * content only when that position is empty, so the page never renders hollow.
 * Everything else (hero copy, gateway targets, the complaints banner) comes from
 * template style parameters.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/** @var Joomla\CMS\Document\HtmlDocument $this */

$app   = Factory::getApplication();
$input = $app->getInput();
$wa    = $this->getWebAssetManager();

$option    = $input->getCmd('option', '');
$view      = $input->getCmd('view', '');
$layout    = $input->getCmd('layout', '');
$task      = $input->getCmd('task', '');
$itemid    = $input->getCmd('Itemid', '');
$sitename  = htmlspecialchars($app->get('sitename'), ENT_QUOTES, 'UTF-8');
$menu      = $app->getMenu()->getActive();
$default   = $app->getMenu()->getDefault();
$isHome    = $menu !== null && $default !== null && $menu->id === $default->id;
$pageclass = $menu !== null ? $menu->getParams()->get('pageclass_sfx', '') : '';
$siteTitle = $this->params->get('siteTitle') ?: $sitename;

$splask = $this->params->get('splaskAttribute', 'data-splask') ?: 'data-splask';
$root   = Uri::root(true);

/* ── SPLaSK analytics ────────────────────────────────────────────────────── */

/**
 * JDN's SPLaSK code is a Matomo tracker: the queue has to exist and be filled
 * before matomo.js loads, which is why this is an inline declaration in the
 * head rather than a plain script tag. The snippet is JDN's own, with the two
 * values that differ per site taken from the style.
 *
 * Both are written into JavaScript through json_encode, so a stray quote in
 * either is a harmless string rather than a broken page.
 */
// The host default is repeated here rather than left to the manifest: a style
// saved before this field existed has no value for it, and the whole point is
// that entering the site ID alone is enough.
$splaskId   = trim((string) $this->params->get('splaskId', ''));
$splaskHost = trim((string) $this->params->get('splaskHost', '')) ?: 'https://splask-analytics.jdn.gov.my/';

// Matomo site IDs are integers, and the host has to be an https origin.
if ($splaskId !== '' && preg_match('/^\d+$/', $splaskId) && preg_match('#^https://[a-z0-9.-]+(?::\d+)?(/[a-z0-9._/-]*)?$#i', $splaskHost)) {
    $splaskHost = rtrim($splaskHost, '/') . '/';

    $this->addScriptDeclaration(
        "/* SPLaSK analytics (Matomo) */\n"
        . "var _paq = window._paq = window._paq || [];\n"
        . ($this->params->get('splaskCookies', 1) ? '' : "_paq.push(['disableCookies']);\n")
        . "_paq.push(['trackPageView']);\n"
        . "_paq.push(['enableLinkTracking']);\n"
        . "(function () {\n"
        . "    var u = " . json_encode($splaskHost, JSON_UNESCAPED_SLASHES) . ";\n"
        . "    _paq.push(['setTrackerUrl', u + 'matomo.php']);\n"
        . "    _paq.push(['setSiteId', " . json_encode($splaskId) . "]);\n"
        . "    var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];\n"
        . "    g.async = true; g.src = u + 'matomo.js'; s.parentNode.insertBefore(g, s);\n"
        . "})();"
    );

    // matomo.js comes from another origin on every page, so let the browser
    // open that connection while it is still parsing the head. No crossorigin:
    // the tracker is fetched as an ordinary script, and a CORS preconnect would
    // open a connection the real request cannot reuse.
    $this->getPreloadManager()->preconnect($splaskHost);
}

$direction = $this->direction === 'rtl' ? 'rtl' : 'ltr';

// Header behaviour is settled here because the theme-token block below needs it
// as well as the markup further down. A sticky header already keeps the
// navigation in view, so the two settings are mutually exclusive.
$stickyHeader = $this->params->get('stickyHeader') ? ' is-sticky' : '';
$stickyMenu   = !$this->params->get('stickyHeader') && $this->params->get('stickyMenu', 0)
    ? ' has-sticky-menu'
    : '';

$wa->usePreset('template.cassiopeia.' . $direction)
    ->useStyle('template.active.language')
    ->useStyle('template.lkim3.' . $direction)
    ->useScript('template.lkim3');

$wa->registerStyle('template.active', '', [], [], ['template.cassiopeia.' . $direction]);
$wa->getAsset('style', 'fontawesome')->setAttribute('rel', 'lazy-stylesheet');

/* ── Theme tokens ────────────────────────────────────────────────────────── */

/**
 * lkim3.css is written entirely against CSS custom properties, so the style can
 * restyle it by redefining those properties. This block goes out after the
 * stylesheet and wins on order, which means an agency whose server forbids
 * writing to template files can still recolour and re-space the whole portal
 * from Template Styles alone.
 *
 * An empty parameter is left out, so the stylesheet's own value stands.
 */
$tokens = [
    '--navy-800'        => $this->params->get('cPrimary'),
    '--navy-950'        => $this->params->get('cPrimaryDark'),
    '--navy-900'        => $this->params->get('cPrimaryDeep'),
    '--navy-700'        => $this->params->get('cPrimaryMid'),
    '--navy-600'        => $this->params->get('cPrimaryLight'),

    '--orange-500'      => $this->params->get('cAccent'),
    '--orange-600'      => $this->params->get('cAccentDark'),
    '--orange-100'      => $this->params->get('cAccentSoft'),

    '--teal-500'        => $this->params->get('cSecondary'),
    '--teal-600'        => $this->params->get('cSecondaryDark'),
    '--teal-100'        => $this->params->get('cSecondarySoft'),

    '--hero-orange'      => $this->params->get('cHeroAccent'),
    '--hero-orange-dark' => $this->params->get('cHeroAccentDark'),
    '--hero-navy'        => $this->params->get('cHeroButtonText'),

    '--sand-50'         => $this->params->get('cSurface'),
    '--sand-100'        => $this->params->get('cSurfaceAlt'),
    '--line'            => $this->params->get('cLine'),

    '--ink-900'         => $this->params->get('cText'),
    '--ink-700'         => $this->params->get('cTextMuted'),
    '--ink-500'         => $this->params->get('cTextSubtle'),
    '--content-link'    => $this->params->get('cContentLink'),
    '--focus-ring'      => $this->params->get('cFocusRing'),

    '--footer-bg'               => $this->params->get('cFooterBg'),
    '--footer-text'             => $this->params->get('cFooterText'),
    '--footer-pattern-opacity'  => $this->params->get('footerPattern'),

    '--base-size'       => $this->params->get('baseSize'),
    '--wrap'            => $this->params->get('wrapWidth'),
    '--section-pad'     => $this->params->get('sectionPad'),
    '--radius-lg'       => $this->params->get('radiusLg'),
    '--radius-md'       => $this->params->get('radiusMd'),
    '--radius-sm'       => $this->params->get('radiusSm'),

    '--header-card-bg'     => $this->params->get('headerCardBg'),
    '--header-card-radius' => $this->params->get('headerCardRadius'),
    '--sticky-menu-bg'     => $stickyMenu ? $this->params->get('stickyMenuBg') : '',

    '--hero-min'        => $this->params->get('heroMin'),
    '--hero-focus'      => $this->params->get('heroFocus'),
    '--hero-shade'      => $this->params->get('heroShade'),
];

// The shadow switches pick between the design's shadow and none, rather than
// asking an editor to write a box-shadow by hand.
if (!$this->params->get('cardShadow', 1)) {
    $tokens['--shadow-card'] = 'none';
    $tokens['--shadow-pop']  = 'none';
}

if (!$this->params->get('headerCardShadow', 1)) {
    $tokens['--header-card-shadow'] = 'none';
}

/* Fonts. The families are named by the editor, so they are quoted here. */
$fontSource = $this->params->get('fontSource', 'google');
$fallback   = $this->params->get('fontFallback') ?: 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';
$headFamily = trim((string) $this->params->get('fontHeading', 'Manrope'));
$bodyFamily = trim((string) $this->params->get('fontBody', 'Inter'));

if ($fontSource === 'system') {
    $tokens['--font-head'] = $fallback;
    $tokens['--font-body'] = $fallback;
} else {
    if ($headFamily !== '') {
        $tokens['--font-head'] = '"' . str_replace('"', '', $headFamily) . '", ' . $fallback;
    }

    if ($bodyFamily !== '') {
        $tokens['--font-body'] = '"' . str_replace('"', '', $bodyFamily) . '", ' . $fallback;
    }
}

$declarations = [];

foreach ($tokens as $token => $value) {
    $value = trim((string) $value);

    // A value may not carry ; } or a comment out of the declaration block.
    if ($value === '' || preg_match('#[;}{]|/\*#', $value)) {
        continue;
    }

    $declarations[] = "\t" . $token . ': ' . $value . ';';
}

if ($declarations) {
    $wa->addInlineStyle(
        ":root {\n" . implode("\n", $declarations) . "\n}",
        ['name' => 'template.lkim3.tokens'],
        [],
        ['template.lkim3.' . $direction]
    );
}

/* ── Custom code ─────────────────────────────────────────────────────────── */

// user.css / user.js are not shipped. Joomla skips an asset whose file does not
// exist, so asking for them is free until someone drops the files in.
if ($this->params->get('useUserFiles', 1)) {
    $wa->useStyle('template.lkim3.user.' . $direction)
        ->useScript('template.lkim3.user');
}

// The two style parameters go out unfiltered — they are Super User input, the
// same trust level as a Custom HTML module.
$customCss = trim((string) $this->params->get('customCss', ''));
$customJs  = trim((string) $this->params->get('customJs', ''));

if ($customCss !== '') {
    $wa->addInlineStyle(
        $customCss,
        ['name' => 'template.lkim3.inline'],
        [],
        ['template.lkim3.' . $direction]
    );
}

if ($customJs !== '' && $this->params->get('customJsPosition', 'body') === 'head') {
    $wa->addInlineScript(
        $customJs,
        ['name' => 'template.lkim3.inline'],
        [],
        ['template.lkim3']
    );
}

// Manrope for headings and Inter for body are what the design is drawn in, but
// both families come from the style, and an agency network that blocks external
// requests can switch the source to "system" and lose nothing but the faces.
if ($fontSource === 'google') {
    $families = [];

    foreach ([[$bodyFamily, $this->params->get('fontBodyWeights', '400;500;600;700')],
              [$headFamily, $this->params->get('fontHeadingWeights', '600;700;800')]] as [$family, $weights]) {
        $family = trim((string) $family);

        if ($family === '') {
            continue;
        }

        $weights    = preg_replace('/[^0-9;]/', '', (string) $weights);
        $families[] = 'family=' . rawurlencode($family)
            . ($weights !== '' ? ':wght@' . $weights : '');
    }

    if ($families) {
        $this->getPreloadManager()->preconnect('https://fonts.googleapis.com/', ['crossorigin' => 'anonymous']);
        $this->getPreloadManager()->preconnect('https://fonts.gstatic.com/', ['crossorigin' => 'anonymous']);
        $wa->registerAndUseStyle(
            'fontscheme.lkim3',
            'https://fonts.googleapis.com/css2?' . implode('&', $families) . '&display=swap',
            [],
            ['rel' => 'lazy-stylesheet', 'crossorigin' => 'anonymous']
        );
    }
}

$this->setMetaData('viewport', 'width=device-width, initial-scale=1');

/* ── Helpers ─────────────────────────────────────────────────────────────── */

/**
 * Turn a template-media path or an external URL into something usable in src=.
 */
$asset = static function (string $path) use ($root): string {
    if ($path === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//|^data:#i', $path)) {
        return $path;
    }

    return $root . '/' . ltrim($path, '/');
};

/** Media shipped with this template. */
$img = static fn(string $file): string => $root . '/media/templates/site/lkim3/images/' . $file;

/** Route a parameter that may be an internal path, a full URL or an Itemid. */
$link = static function (?string $path) use ($root): string {
    $path = trim((string) $path);

    if ($path === '') {
        return '#';
    }

    if (preg_match('#^(https?:)?//|^(mailto|tel):|^\##i', $path)) {
        return $path;
    }

    return $root . '/' . ltrim($path, '/');
};

/**
 * The hero headline takes two bits of markup: {a}…{/a} around the words that
 * carry the accent colour, and {br} where the line should break.
 */
$accent = static function (string $text): string {
    $text = htmlspecialchars($text, ENT_COMPAT, 'UTF-8');

    return str_replace(
        ['{a}', '{/a}', '{br}'],
        ['<span class="accent">', '</span>', '<br>'],
        $text
    );
};

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_COMPAT, 'UTF-8');
$a = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/* ── Shell ───────────────────────────────────────────────────────────────── */

$logoFile = $this->params->get('logoFile', 'media/templates/site/lkim3/images/logo.png');
$jataFile = $this->params->get('jataFile');

$wrapper = $this->params->get('fluidContainer') ? 'wrapper-fluid' : 'wrapper-static';

$hasClass = '';

if ($this->countModules('sidebar-left', true)) {
    $hasClass .= ' has-sidebar-left';
}

if ($this->countModules('sidebar-right', true)) {
    $hasClass .= ' has-sidebar-right';
}

$lastUpdate = null;

if ($this->params->get('showLastUpdate', 1)) {
    $db    = Factory::getContainer()->get(DatabaseInterface::class);
    $query = $db->getQuery(true)
        ->select('MAX(' . $db->quoteName('modified') . ')')
        ->from($db->quoteName('#__content'))
        ->where($db->quoteName('state') . ' = 1');
    $db->setQuery($query);
    $lastUpdate = $db->loadResult();
}

$footerCols = array_filter(
    ['footer-a', 'footer-b', 'footer-c', 'footer-d'],
    fn($pos) => $this->countModules($pos, true)
);

$socials = array_filter([
    'facebook'  => $this->params->get('facebookUrl'),
    'twitter'   => $this->params->get('twitterUrl'),
    'youtube'   => $this->params->get('youtubeUrl'),
    'instagram' => $this->params->get('instagramUrl'),
]);

/* ── Homepage content ────────────────────────────────────────────────────── */

$heroImage = $this->params->get('heroImage', 'media/templates/site/lkim3/images/banner.jpg');
$heroAlt   = $this->params->get('heroAlt') ?: Text::_('TPL_LKIM3_HERO_ALT');
$heroTitle = $this->params->get('heroTitle') ?: Text::_('TPL_LKIM3_HERO_TITLE');
$heroLead  = $this->params->get('heroLead') ?: Text::_('TPL_LKIM3_HERO_LEAD');
$heroCta   = $this->params->get('heroCtaText') ?: Text::_('TPL_LKIM3_HERO_CTA');
$heroCta2  = $this->params->get('heroCta2Text') ?: Text::_('TPL_LKIM3_HERO_CTA2');

// Three audience gateways, in the order the design stacks them.
$gateways = [
    ['c1', 'TPL_LKIM3_GATE_PUBLIC',  $this->params->get('gateway1Link', '/hubungi-kami')],
    ['c2', 'TPL_LKIM3_GATE_NELAYAN', $this->params->get('gateway2Link', '/perkhidmatan')],
    ['c3', 'TPL_LKIM3_GATE_STAFF',   $this->params->get('gateway3Link', '/korporat')],
];

// The four supporting service cards. Labels come from the language files, the
// destinations from the style so they can be repointed without touching code.
$serviceCards = [
    ['pasaran-pendapatan.png', 'TPL_LKIM3_SVC_PASARAN', $this->params->get('svcLink1')],
    ['dana-nelayan.png',       'TPL_LKIM3_SVC_DANA',    $this->params->get('svcLink2')],
    ['skim-sosial.png',        'TPL_LKIM3_SVC_SOSIAL',  $this->params->get('svcLink3')],
    ['perumahan-nelayan.png',  'TPL_LKIM3_SVC_RUMAH',   $this->params->get('svcLink4')],
];

$gallery = [
    ['gallery-1.jpg', 'TPL_LKIM3_GAL_1'],
    ['gallery-2.jpg', 'TPL_LKIM3_GAL_2'],
    ['gallery-3.jpg', 'TPL_LKIM3_GAL_3'],
    ['gallery-4.jpg', 'TPL_LKIM3_GAL_4'],
    ['gallery-5.jpg', 'TPL_LKIM3_GAL_5'],
];

$agencyLogos = [
    ['mardi.png',             'MARDI'],
    ['mygov.png',             'MyGOV'],
    ['jabatan-perikanan.png', 'Jabatan Perikanan Malaysia'],
    ['fama.png',              'FAMA'],
    ['kada.png',              'KADA'],
    ['agrobank.png',          'Agrobank'],
    ['maqis.png',             'MAQIS'],
    ['v-logo.png',            'Agensi Kerajaan'],
];

/**
 * A band draws either the LKIM-3 design layout or whatever modules sit in its
 * position. The design is the default so a fresh install matches the mockup;
 * an editor switches a band to "module" once there is real content for it.
 */
$fromModules = fn(string $param, string $position): bool
    => $this->params->get($param, 'design') === 'module' && $this->countModules($position, true);

// The design layout for the news band shows the portal's own three most recent
// articles rather than the mockup's placeholder cards.
$news = [];

if ($isHome && $this->params->get('showNews', 1) && !$fromModules('srcNews', 'announcements')) {
    $db     = Factory::getContainer()->get(DatabaseInterface::class);
    $levels = $app->getIdentity()->getAuthorisedViewLevels() ?: [1];
    $query  = $db->getQuery(true)
        ->select($db->quoteName(['a.id', 'a.title', 'a.alias', 'a.catid', 'a.images', 'a.publish_up']))
        ->select($db->quoteName('c.title', 'category_title'))
        ->select($db->quoteName('c.alias', 'category_alias'))
        ->from($db->quoteName('#__content', 'a'))
        ->innerJoin($db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
        ->where($db->quoteName('a.state') . ' = 1')
        ->where($db->quoteName('c.published') . ' = 1')
        ->whereIn($db->quoteName('a.access'), $levels)
        // Falang keeps one content tree, but the migration also created
        // standalone English pages; without this the band mixes languages.
        ->whereIn($db->quoteName('a.language'), ['*', $app->getLanguage()->getTag()], ParameterType::STRING)
        ->order($db->quoteName('a.publish_up') . ' DESC');
    $db->setQuery($query, 0, 3);

    try {
        $news = $db->loadObjectList() ?: [];
    } catch (\Throwable $ex) {
        $news = [];
    }
}

$newsAccents  = ['acc-orange', 'acc-teal', 'acc-navy'];
$newsFallback = ['news-1.jpg', 'news-2.jpg', 'news-3.jpg'];

/**
 * The page body is a list of sections the style owns. script.php seeds that
 * list into every style on install, so this fallback only matters for a style
 * created before the template was installed — or one whose list someone
 * emptied. Both read the same file, so the two can never drift.
 */
$rows = $this->params->get('sections');
$rows = $rows ? (array) $rows : require __DIR__ . '/sections/defaults.php';
$sections = [];

foreach ($rows as $row) {
    $sections[] = new Registry($row);
}

// An interior page still needs its content even if someone removes every row.
if (!$isHome) {
    $hasContent = array_filter($sections, fn($s) => (string) $s->get('type') === 'content');

    if (!$hasContent) {
        $sections[] = new Registry(['type' => 'content']);
    }
}

/* ── Inline SVG used repeatedly ──────────────────────────────────────────── */

$svgArrow   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$svgArrowNe = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M7 17 17 7M9 7h8v8"/></svg>';
$svgCheck   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true" focusable="false"><path d="M5 13l4 4L19 7"/></svg>';
$svgDate    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>';

$svgSocial = [
    'facebook'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M15 8h2V5h-2a4 4 0 0 0-4 4v2H9v3h2v7h3v-7h2.4l.6-3H14V9c0-.6.4-1 1-1Z"/></svg>',
    'twitter'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M4 4l16 16M20 4 4 20"/></svg>',
    'youtube'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="6" width="18" height="12" rx="3"/><path d="M11 10l4 2-4 2Z" fill="currentColor" stroke="none"/></svg>',
    'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor" stroke="none"/></svg>',
];

$socialLabel = [
    'facebook'  => 'Facebook',
    'twitter'   => 'X / Twitter',
    'youtube'   => 'YouTube',
    'instagram' => 'Instagram',
];

$utilIcons = [
    ['soalan-lazim.png', 'TPL_LKIM3_UTIL_FAQ',      $this->params->get('utilFaq', '/soalan-lazim')],
    ['hubungi.png',      'TPL_LKIM3_UTIL_CONTACT',  $this->params->get('utilContact', '/hubungi-kami')],
    ['maklumbalas.png',  'TPL_LKIM3_UTIL_FEEDBACK', $this->params->get('utilFeedback', '/hubungi-kami')],
    ['peta-laman.png',   'TPL_LKIM3_UTIL_SITEMAP',  $this->params->get('utilSitemap', '/peta-laman')],
];

$legalLinks = array_filter([
    'TPL_LKIM3_LEGAL_TERMS'      => $this->params->get('legalTerms'),
    'TPL_LKIM3_LEGAL_PRIVACY'    => $this->params->get('legalPrivacy'),
    'TPL_LKIM3_LEGAL_SECURITY'   => $this->params->get('legalSecurity'),
    'TPL_LKIM3_LEGAL_DISCLAIMER' => $this->params->get('legalDisclaimer'),
]);
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">

<head>
    <jdoc:include type="metas" />
    <jdoc:include type="styles" />
    <jdoc:include type="scripts" />
</head>

<body id="top" class="site lkim lkim3 <?php echo $option
    . ' ' . $wrapper
    . ' view-' . $view
    . ($layout ? ' layout-' . $layout : ' no-layout')
    . ($task ? ' task-' . $task : ' no-task')
    . ($itemid ? ' itemid-' . $itemid : '')
    . ($pageclass ? ' ' . $pageclass : '')
    . ($isHome ? ' is-home' : ' is-inner')
    . $stickyMenu
    . $hasClass
    . ($this->direction == 'rtl' ? ' rtl' : '');
?>">

    <nav class="lkim-skiplinks" aria-label="<?php echo Text::_('TPL_LKIM_SKIPLINKS'); ?>">
        <a class="lkim-skiplink" href="#main-content"><?php echo Text::_('TPL_LKIM_SKIP_TO_CONTENT'); ?></a>
        <a class="lkim-skiplink" href="#main-navigation"><?php echo Text::_('TPL_LKIM_SKIP_TO_NAV'); ?></a>
        <a class="lkim-skiplink" href="#site-footer"><?php echo Text::_('TPL_LKIM_SKIP_TO_FOOTER'); ?></a>
    </nav>

    <?php if ($this->params->get('showGovBanner', 1)) : ?>
        <section class="lkim-govbar" aria-label="<?php echo Text::_('TPL_LKIM_GOV_BANNER'); ?>" <?php echo $splask; ?>="gov-identification">
            <details class="wrap lkim-govbar-inner">
                <summary class="lkim-govbar-summary">
                    <span class="lkim-govbar-brand">
                        <svg class="lkim-govbar-flag" viewBox="0 0 32 16" width="28" height="14" role="img" aria-label="Jalur Gemilang" focusable="false">
                            <rect width="32" height="16" fill="#fff" />
                            <g fill="#d10525">
                                <path d="M16 0h16v1.14H16zM16 2.29h16v1.14H16zM16 4.57h16v1.14H16zM16 6.86h16v1.14H16z" />
                                <path d="M0 9.14h32v1.14H0zM0 11.43h32v1.14H0zM0 13.71h32v1.14H0z" />
                            </g>
                            <path d="M0 0h16v9.15H0z" fill="#102a7e" />
                            <path d="M8.39 1.71a3.06 3.06 0 1 0 0 5.72 3.4 3.4 0 1 1 0-5.72z" fill="#fad209" />
                            <path d="m9.98 1.69.26 1.75 1-1.47-.54 1.7 1.54-.9-1.22 1.3 1.77-.14-1.65.63 1.65.64-1.77-.14 1.22 1.29-1.54-.89.53 1.69-.99-1.47-.26 1.76-.26-1.76-.99 1.47.53-1.69-1.53.89 1.21-1.29-1.77.14 1.65-.64-1.65-.63 1.77.14-1.21-1.3 1.53.9-.53-1.7 1 1.47.25-1.75z" fill="#fad209" />
                        </svg>
                        <span class="lkim-govbar-title"><?php echo Text::_('TPL_LKIM_GOV_BRAND'); ?></span>
                    </span>
                    <span class="lkim-govbar-toggle">
                        <?php echo Text::_('TPL_LKIM_GOV_TOGGLE'); ?>
                        <span class="lkim-govbar-chevron" aria-hidden="true"></span>
                    </span>
                </summary>
                <div class="lkim-govbar-panel">
                    <div class="lkim-govbar-item">
                        <span class="lkim-govbar-ico" aria-hidden="true">&#127963;&#65039;</span>
                        <div>
                            <p class="lkim-govbar-h"><?php echo Text::_('TPL_LKIM_GOV_DOMAIN_TITLE'); ?></p>
                            <p class="lkim-govbar-p"><?php echo Text::_('TPL_LKIM_GOV_DOMAIN_DESC'); ?></p>
                        </div>
                    </div>
                    <div class="lkim-govbar-item">
                        <span class="lkim-govbar-ico" aria-hidden="true">&#128274;</span>
                        <div>
                            <p class="lkim-govbar-h"><?php echo Text::_('TPL_LKIM_GOV_HTTPS_TITLE'); ?></p>
                            <p class="lkim-govbar-p"><?php echo Text::_('TPL_LKIM_GOV_HTTPS_DESC'); ?></p>
                        </div>
                    </div>
                </div>
            </details>
        </section>
    <?php endif; ?>

    <div class="lk3-stage">

        <header class="site-header<?php echo $stickyHeader; ?>">
            <div class="wrap header-card-row">
                <div class="header-card">
                    <a href="<?php echo $this->baseurl; ?>/" class="brand">
                        <?php if ($jataFile) : ?>
                            <img class="brand-jata" src="<?php echo $a($asset($jataFile)); ?>" alt="" loading="eager" decoding="async">
                        <?php endif; ?>
                        <?php if ($logoFile) : ?>
                            <img class="brand-mark" src="<?php echo $a($asset($logoFile)); ?>" alt="<?php echo $a($siteTitle); ?>" loading="eager" decoding="async">
                        <?php endif; ?>
                        <span class="brand-text">
                            <small class="eyebrow"><?php echo Text::_('TPL_LKIM3_BRAND_EYEBROW'); ?></small>
                            <strong><?php echo $e($siteTitle); ?></strong>
                            <?php if ($this->params->get('siteDescription')) : ?>
                                <span><?php echo $e($this->params->get('siteDescription')); ?></span>
                            <?php endif; ?>
                        </span>
                    </a>

                    <div class="header-actions">
                        <div class="util-icons">
                            <?php foreach ($utilIcons as [$icon, $label, $href]) : ?>
                                <a href="<?php echo $a($link($href)); ?>" aria-label="<?php echo $a(Text::_($label)); ?>" title="<?php echo $a(Text::_($label)); ?>">
                                    <img src="<?php echo $a($img($icon)); ?>" alt="" loading="lazy" decoding="async">
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <div class="util-divider" aria-hidden="true"></div>

                        <?php if ($this->countModules('topbar', true)) : ?>
                            <div class="lang-switch" <?php echo $splask; ?>="language-switcher">
                                <jdoc:include type="modules" name="topbar" style="none" />
                            </div>
                        <?php endif; ?>

                        <?php if ($this->params->get('showAccessBar', 1)) : ?>
                            <div class="lk3-pop-wrap">
                                <button class="icon-btn" type="button" data-lkim-a11y aria-expanded="false" aria-controls="lk3-a11y"
                                    aria-label="<?php echo $a(Text::_('TPL_LKIM3_A11Y_TOOLS')); ?>" title="<?php echo $a(Text::_('TPL_LKIM3_A11Y_TOOLS')); ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="4.5" r="1.6" fill="currentColor" stroke="none" /><path d="M4 8.5c2.6.9 5.2 1.4 8 1.4s5.4-.5 8-1.4M12 9.9V21m0-7 4 7M12 14l-4 7" /></svg>
                                </button>
                                <div class="lk3-pop" id="lk3-a11y" hidden>
                                    <p class="lk3-pop-h"><?php echo Text::_('TPL_LKIM_TEXTSIZE'); ?></p>
                                    <div class="lkim-textsize" role="group" aria-label="<?php echo $a(Text::_('TPL_LKIM_TEXTSIZE')); ?>" <?php echo $splask; ?>="text-resize">
                                        <button type="button" class="lkim-tsbtn" data-lkim-text="decrease" aria-label="<?php echo $a(Text::_('TPL_LKIM_TEXTSIZE_SMALLER')); ?>">A&minus;</button>
                                        <button type="button" class="lkim-tsbtn" data-lkim-text="reset" aria-label="<?php echo $a(Text::_('TPL_LKIM_TEXTSIZE_RESET')); ?>">A</button>
                                        <button type="button" class="lkim-tsbtn" data-lkim-text="increase" aria-label="<?php echo $a(Text::_('TPL_LKIM_TEXTSIZE_LARGER')); ?>">A+</button>
                                    </div>
                                    <button type="button" class="lkim-tsbtn lkim-contrast" data-lkim-contrast aria-pressed="false" <?php echo $splask; ?>="high-contrast">
                                        <?php echo Text::_('TPL_LKIM_CONTRAST'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($this->countModules('search', true)) : ?>
                            <div class="lk3-pop-wrap">
                                <button class="icon-btn" type="button" data-lkim-search aria-expanded="false" aria-controls="lk3-search"
                                    aria-label="<?php echo $a(Text::_('TPL_LKIM3_SEARCH')); ?>" title="<?php echo $a(Text::_('TPL_LKIM3_SEARCH')); ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
                                </button>
                                <div class="lk3-pop lk3-pop-search" id="lk3-search" hidden <?php echo $splask; ?>="site-search">
                                    <jdoc:include type="modules" name="search" style="none" />
                                </div>
                            </div>
                        <?php endif; ?>

                        <button class="icon-btn nav-toggle" type="button" data-lkim-navtoggle aria-expanded="false" aria-controls="mainNav"
                            aria-label="<?php echo $a(Text::_('TPL_LKIM_MENU')); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <?php $menuFromModule = $this->params->get('menuSource', 'design') === 'module' && $this->countModules('menu', true); ?>
            <?php if ($menuFromModule || $this->params->get('menuSource', 'design') === 'design') : ?>
                <nav class="hero-nav-row" id="main-navigation" aria-label="<?php echo $a(Text::_('TPL_LKIM_MAIN_MENU')); ?>">
                    <div class="wrap">
                        <?php if ($menuFromModule) : ?>
                            <jdoc:include type="modules" name="menu" style="none" />
                        <?php else : ?>
                            <?php require __DIR__ . '/megamenu.php'; ?>
                        <?php endif; ?>
                    </div>
                    <hr class="hero-nav-divider">
                </nav>
            <?php endif; ?>
        </header>

        <div class="nav-backdrop" data-lkim-backdrop hidden></div>

        <?php if ($isHome) : ?>
            <section class="hero">
                <div class="hero-bg">
                    <img src="<?php echo $a($asset($heroImage)); ?>" alt="<?php echo $a($heroAlt); ?>" fetchpriority="high" decoding="async">
                </div>
                <div class="hero-inner">
                    <h1><?php echo $accent($heroTitle); ?></h1>
                    <p class="lede"><?php echo $e($heroLead); ?></p>
                    <div class="hero-actions">
                        <?php if ($heroCta) : ?>
                            <a href="<?php echo $a($link($this->params->get('heroCtaLink'))); ?>" class="btn btn-primary">
                                <?php echo $e($heroCta); ?><?php echo $svgArrow; ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($heroCta2) : ?>
                            <a href="<?php echo $a($link($this->params->get('heroCta2Link'))); ?>" class="btn btn-ghost">
                                <?php echo $e($heroCta2); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php else : ?>
            <section class="page-hero">
                <div class="wrap page-hero-inner">
                    <?php if ($this->countModules('breadcrumbs', true)) : ?>
                        <jdoc:include type="modules" name="breadcrumbs" style="none" />
                    <?php endif; ?>
                    <?php
                    /**
                     * A <p>, not an <h1>: com_content prints its own heading for
                     * the page inside <main>, and two level-one headings on one
                     * page is exactly what the accessibility audit flags.
                     */
                    ?>
                    <p class="page-hero-title"><?php echo $e($menu !== null ? ($menu->getParams()->get('page_heading') ?: $menu->title) : $siteTitle); ?></p>
                    <?php if ($menu !== null && $menu->getParams()->get('menu-meta_description')) : ?>
                        <p class="lede"><?php echo $e($menu->getParams()->get('menu-meta_description')); ?></p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>


    <?php
    /**
     * The page body between the hero and the footer is a list of sections the
     * style owns: which ones, in what order, and how each is spaced and
     * coloured. Anything the design does not ship can be added as a "modules"
     * or "html" row, which is how a new band gets built without editing this
     * file.
     *
     * Each row renders sections/<type>.php. Most of those emit inner content
     * only and take the standard band wrapper below; the two that cannot —
     * the gateway cards, which overlap the hero, and the page content, which
     * is the document's <main> — carry their own and are listed in $selfWrap.
     */
    $selfWrap = ['gateways' => true, 'content' => true];

    $backgrounds = [
        'surface'     => 'lk3-bg-surface',
        'surface-alt' => 'lk3-bg-surface-alt',
        'primary'     => 'lk3-bg-primary',
    ];

    $spacings = ['tight' => 'tight', 'none' => 'lk3-pad-none'];

    foreach ($sections as $section) {
        $type = preg_replace('/[^a-z]/', '', (string) $section->get('type', ''));
        $file = __DIR__ . '/sections/' . $type . '.php';

        if ($type === '' || !$section->get('enabled', 1) || !is_file($file)) {
            continue;
        }

        // Only the page content belongs on an interior page; the rest of the
        // list describes the home page.
        if (!$isHome && $type !== 'content') {
            continue;
        }

        $background = (string) $section->get('background', 'none');
        $classes    = ['lk3-s-' . $type];

        if (isset($backgrounds[$background])) {
            $classes[] = $backgrounds[$background];
        }

        if (isset($spacings[(string) $section->get('spacing', 'normal')])) {
            $classes[] = $spacings[(string) $section->get('spacing', 'normal')];
        }

        if ($extra = trim((string) $section->get('cssClass', ''))) {
            $classes[] = preg_replace('/[^a-zA-Z0-9 _-]/', '', $extra);
        }

        $secClass = $a(implode(' ', array_filter($classes)));

        $anchor = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $section->get('anchor', ''));
        $secId  = $anchor !== '' ? ' id="' . $anchor . '"' : '';

        // A custom background is free text so it can be a gradient as well as a
        // colour; anything that could break out of the attribute is dropped.
        $custom   = trim((string) $section->get('bgCustom', ''));
        $secStyle = $background === 'custom' && $custom !== '' && !preg_match('/[;"<>]/', $custom)
            ? ' style="background:' . $a($custom) . '"'
            : '';

        if (isset($selfWrap[$type])) {
            require $file;
            continue;
        }

        // Buffer first: a section whose source turns out to be empty — a module
        // position with nothing published in it, say — should take its band
        // away with it rather than leave a padded, coloured, empty strip.
        ob_start();
        require $file;
        $body = ob_get_clean();

        if (trim($body) === '') {
            continue;
        }
        ?>
        <section class="section <?php echo $secClass; ?>"<?php echo $secId . $secStyle; ?>>
            <?php if ((string) $section->get('width', 'contained') === 'full') : ?>
                <?php echo $body; ?>
            <?php else : ?>
                <div class="wrap"><?php echo $body; ?></div>
            <?php endif; ?>
        </section>
        <?php
    }
    ?>

    <footer class="site-footer" id="site-footer">
        <div class="wrap footer-top">
            <div class="footer-brand">
                <a href="<?php echo $this->baseurl; ?>/" class="brand">
                    <span class="brand-text"><strong><?php echo $e($siteTitle); ?></strong></span>
                </a>
                <p><?php echo Text::_('TPL_LKIM3_FOOTER_BLURB'); ?></p>
                <?php if ($socials) : ?>
                    <div class="footer-social">
                        <?php foreach ($socials as $key => $url) : ?>
                            <a href="<?php echo $a($url); ?>" rel="noopener noreferrer" target="_blank" aria-label="<?php echo $a($socialLabel[$key]); ?>">
                                <?php echo $svgSocial[$key]; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php foreach ($footerCols as $col) : ?>
                <div class="footer-col">
                    <jdoc:include type="modules" name="<?php echo $col; ?>" style="lkimfooter" />
                </div>
            <?php endforeach; ?>

            <?php if ($this->params->get('showFooterContact', 1)) : ?>
            <div class="footer-col">
                <h4><?php echo Text::_('TPL_LKIM3_FOOTER_CONTACT'); ?></h4>
                <ul class="footer-contact">
                    <?php if ($this->params->get('agencyAddress')) : ?>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M12 21s7-6.6 7-12a7 7 0 1 0-14 0c0 5.4 7 12 7 12Z" /><circle cx="12" cy="9" r="2.4" /></svg>
                            <span <?php echo $splask; ?>="agency-address"><?php echo nl2br($e($this->params->get('agencyAddress'))); ?></span>
                        </li>
                    <?php endif; ?>
                    <?php if ($this->params->get('agencyPhone')) : ?>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.9.6 2.7a2 2 0 0 1-.5 2.1L8 9.7a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.5 2.7.6a2 2 0 0 1 1.7 2Z" /></svg>
                            <a href="tel:<?php echo $a(preg_replace('/[^0-9+]/', '', $this->params->get('agencyPhone'))); ?>" <?php echo $splask; ?>="agency-phone"><?php echo $e($this->params->get('agencyPhone')); ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if ($this->params->get('agencyFax')) : ?>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="6" y="3" width="12" height="5" rx="1" /><rect x="3" y="8" width="18" height="8" rx="2" /><rect x="6" y="16" width="12" height="5" rx="1" /></svg>
                            <span><?php echo $e($this->params->get('agencyFax')); ?></span>
                        </li>
                    <?php endif; ?>
                    <?php if ($this->params->get('agencyEmail')) : ?>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>
                            <a href="mailto:<?php echo $a($this->params->get('agencyEmail')); ?>" <?php echo $splask; ?>="agency-email"><?php echo $e($this->params->get('agencyEmail')); ?></a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($this->countModules('footer', true)) : ?>
            <div class="wrap footer-extra">
                <jdoc:include type="modules" name="footer" style="none" />
            </div>
        <?php endif; ?>

        <div class="wrap footer-bottom">
            <div class="footer-bottom-left">
                <span <?php echo $splask; ?>="copyright">
                    <?php echo Text::_('TPL_LKIM3_COPYRIGHT'); ?> &copy; <?php echo date('Y'); ?> <?php echo $e($siteTitle); ?>
                </span>
                <?php if ($lastUpdate) : ?>
                    <span <?php echo $splask; ?>="last-updated">
                        <?php echo Text::_('TPL_LKIM_LAST_UPDATED'); ?>:
                        <time datetime="<?php echo HTMLHelper::_('date', $lastUpdate, 'Y-m-d'); ?>"><?php echo HTMLHelper::_('date', $lastUpdate, 'd F Y'); ?></time>
                    </span>
                <?php endif; ?>
                <?php if ($this->params->get('showVisitorCounter', 1)) : ?>
                    <span class="visitor-count" <?php echo $splask; ?>="visitor-counter">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></svg>
                        <?php echo Text::_('TPL_LKIM_VISITORS'); ?>: <span data-lkim-counter>&mdash;</span>
                    </span>
                <?php endif; ?>
            </div>
            <div class="legal">
                <?php foreach ($legalLinks as $label => $href) : ?>
                    <a href="<?php echo $a($link($href)); ?>"><?php echo Text::_($label); ?></a>
                <?php endforeach; ?>
                <span <?php echo $splask; ?>="best-viewed"><?php echo Text::_('TPL_LKIM_BEST_VIEWED'); ?></span>
            </div>
        </div>
    </footer>

    <?php if ($this->params->get('backTop', 1)) : ?>
        <a href="#top" class="lkim-backtotop" aria-label="<?php echo $a(Text::_('TPL_LKIM_BACKTOTOP')); ?>">
            <span aria-hidden="true">&uarr;</span>
        </a>
    <?php endif; ?>

    <jdoc:include type="modules" name="debug" style="none" />

    <?php if ($customJs !== '' && $this->params->get('customJsPosition', 'body') !== 'head') : ?>
        <script><?php echo $customJs; ?></script>
    <?php endif; ?>
</body>

</html>
