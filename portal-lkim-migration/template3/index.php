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

    <?php if ($isHome && $this->params->get('showGateways', 1)) : ?>
        <div class="wrap quicklinks">
            <?php if ($fromModules('srcGateways', 'quicklinks')) : ?>
                <jdoc:include type="modules" name="quicklinks" style="none" />
            <?php else : ?>
                <div class="grid3">
                    <?php foreach ($gateways as [$tone, $label, $href]) : ?>
                        <a href="<?php echo $a($link($href)); ?>" class="qcard <?php echo $tone; ?>">
                            <span class="qc-label"><?php echo str_replace('|', '<br>', $e(Text::_($label))); ?></span>
                            <span class="qc-foot">
                                <span><?php echo $e(Text::_($label . '_DESC')); ?></span>
                                <span class="arrow-chip"><?php echo $svgArrowNe; ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($isHome && $this->params->get('showServices', 1)) : ?>
        <section class="section" id="perkhidmatan">
            <div class="wrap">
                <div class="section-head">
                    <div>
                        <div class="mark"><span aria-hidden="true"></span><small><?php echo Text::_('TPL_LKIM3_SVC_EYEBROW'); ?></small></div>
                        <h2><?php echo Text::_('TPL_LKIM3_SVC_TITLE'); ?></h2>
                        <p><?php echo Text::_('TPL_LKIM3_SVC_LEAD'); ?></p>
                    </div>
                    <a href="<?php echo $a($link($this->params->get('servicesMoreLink'))); ?>" class="link-more">
                        <?php echo Text::_('TPL_LKIM3_SVC_MORE'); ?><?php echo $svgArrow; ?>
                    </a>
                </div>

                <?php if ($fromModules('srcServices', 'services')) : ?>
                    <div class="services-modules">
                        <jdoc:include type="modules" name="services" style="lkimsection" />
                    </div>
                <?php else : ?>
                    <div class="services-grid">
                        <div class="service-feature">
                            <span class="sf-icon"><img src="<?php echo $a($img('icons/bantuan-sarahidup.png')); ?>" alt="" loading="lazy" decoding="async"></span>
                            <h3><?php echo Text::_('TPL_LKIM3_SVC_FEATURE'); ?></h3>
                            <p><?php echo Text::_('TPL_LKIM3_SVC_FEATURE_DESC'); ?></p>
                            <ul class="checks">
                                <li><?php echo $svgCheck . Text::_('TPL_LKIM3_SVC_FEATURE_CHECK1'); ?></li>
                                <li><?php echo $svgCheck . Text::_('TPL_LKIM3_SVC_FEATURE_CHECK2'); ?></li>
                            </ul>
                            <a href="<?php echo $a($link($this->params->get('featureLink'))); ?>" class="btn btn-primary btn-sm">
                                <?php echo Text::_('TPL_LKIM3_SVC_FEATURE_CTA'); ?><?php echo $svgArrow; ?>
                            </a>
                        </div>

                        <?php foreach ($serviceCards as [$icon, $label, $href]) : ?>
                            <div class="service-card">
                                <span class="sc-icon"><img src="<?php echo $a($img('icons/' . $icon)); ?>" alt="" loading="lazy" decoding="async"></span>
                                <h3><?php echo Text::_($label); ?></h3>
                                <p><?php echo Text::_($label . '_DESC'); ?></p>
                                <a href="<?php echo $a($link($href)); ?>" class="sc-link">
                                    <?php echo Text::_($label . '_CTA'); ?><?php echo $svgArrow; ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($isHome && $this->params->get('showGallery', 1)) : ?>
        <section class="section tight" id="media">
            <div class="wrap">
                <div class="section-head">
                    <div>
                        <div class="mark"><span aria-hidden="true"></span><small><?php echo Text::_('TPL_LKIM3_GAL_EYEBROW'); ?></small></div>
                        <h2><?php echo Text::_('TPL_LKIM3_GAL_TITLE'); ?></h2>
                    </div>
                    <?php if ($socials) : ?>
                        <div class="gallery-tabs">
                            <?php foreach ($socials as $key => $url) : ?>
                                <a href="<?php echo $a($url); ?>" rel="noopener noreferrer" target="_blank"
                                    aria-label="<?php echo $a($socialLabel[$key]); ?>" title="<?php echo $a($socialLabel[$key]); ?>">
                                    <?php echo $svgSocial[$key]; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($fromModules('srcGallery', 'highlights')) : ?>
                    <jdoc:include type="modules" name="highlights" style="none" />
                <?php else : ?>
                    <div class="gallery-strip">
                        <?php foreach ($gallery as [$file, $label]) : ?>
                            <figure class="g-item">
                                <img src="<?php echo $a($img($file)); ?>" alt="<?php echo $a(Text::_($label)); ?>" loading="lazy" decoding="async">
                                <figcaption class="g-caption"><?php echo Text::_($label); ?></figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($isHome && $this->params->get('showNews', 1) && ($news || $fromModules('srcNews', 'announcements'))) : ?>
        <section class="section lk3-news-band">
            <div class="wrap">
                <div class="section-head">
                    <div>
                        <div class="mark"><span aria-hidden="true"></span><small><?php echo Text::_('TPL_LKIM3_NEWS_EYEBROW'); ?></small></div>
                        <h2><?php echo Text::_('TPL_LKIM3_NEWS_TITLE'); ?></h2>
                        <p><?php echo Text::_('TPL_LKIM3_NEWS_LEAD'); ?></p>
                    </div>
                    <a href="<?php echo $a($link($this->params->get('newsMoreLink'))); ?>" class="link-more">
                        <?php echo Text::_('TPL_LKIM3_NEWS_MORE'); ?><?php echo $svgArrow; ?>
                    </a>
                </div>

                <?php if ($fromModules('srcNews', 'announcements')) : ?>
                    <jdoc:include type="modules" name="announcements" style="none" />
                <?php else : ?>
                    <div class="news-grid">
                        <?php foreach ($news as $i => $row) : ?>
                            <?php
                            $images = json_decode((string) $row->images);
                            $thumb  = $images->image_intro ?? $images->image_fulltext ?? '';
                            $thumb  = $thumb ? $asset(HTMLHelper::cleanImageURL($thumb)->url) : $img($newsFallback[$i % 3]);
                            $route  = RouteHelper::getArticleRoute($row->id . ':' . $row->alias, $row->catid . ':' . $row->category_alias);
                            ?>
                            <article class="news-card <?php echo $newsAccents[$i % 3]; ?>">
                                <div class="news-thumb">
                                    <span class="news-tag"><?php echo $e($row->category_title); ?></span>
                                    <img src="<?php echo $a($thumb); ?>" alt="" loading="lazy" decoding="async">
                                </div>
                                <div class="news-body">
                                    <div class="news-date">
                                        <?php echo $svgDate; ?>
                                        <time datetime="<?php echo HTMLHelper::_('date', $row->publish_up, 'Y-m-d'); ?>">
                                            <?php echo HTMLHelper::_('date', $row->publish_up, Text::_('DATE_FORMAT_LC3')); ?>
                                        </time>
                                    </div>
                                    <h3><a href="<?php echo $a($route); ?>"><?php echo $e($row->title); ?></a></h3>
                                    <a href="<?php echo $a($route); ?>" class="read">
                                        <?php echo Text::_('TPL_LKIM3_READ_MORE'); ?><?php echo $svgArrow; ?>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <main id="main-content" class="lkim-main">
        <div class="wrap lkim-layout<?php echo $hasClass; ?>">

            <?php if ($this->countModules('sidebar-left', true)) : ?>
                <aside class="lkim-sidebar lkim-sidebar-left">
                    <jdoc:include type="modules" name="sidebar-left" style="lkimcard" />
                </aside>
            <?php endif; ?>

            <div class="lkim-content">
                <jdoc:include type="modules" name="top-a" style="lkimcard" />
                <jdoc:include type="modules" name="main-top" style="lkimcard" />
                <jdoc:include type="message" />
                <jdoc:include type="component" />
                <jdoc:include type="modules" name="main-bottom" style="lkimcard" />
                <jdoc:include type="modules" name="bottom-a" style="lkimcard" />
            </div>

            <?php if ($this->countModules('sidebar-right', true)) : ?>
                <aside class="lkim-sidebar lkim-sidebar-right">
                    <jdoc:include type="modules" name="sidebar-right" style="lkimcard" />
                </aside>
            <?php endif; ?>
        </div>
    </main>

    <?php if ($isHome && $this->params->get('showCta', 1)) : ?>
        <section class="section tight" id="aduan">
            <div class="wrap">
                <div class="cta-banner">
                    <div class="cta-content">
                        <div class="mark"><span aria-hidden="true"></span><small><?php echo Text::_('TPL_LKIM3_CTA_EYEBROW'); ?></small></div>
                        <h2><?php echo Text::_('TPL_LKIM3_CTA_TITLE'); ?></h2>
                        <p><?php echo Text::_('TPL_LKIM3_CTA_LEAD'); ?></p>
                    </div>
                    <div class="cta-actions">
                        <a href="<?php echo $a($link($this->params->get('ctaLink'))); ?>" class="btn btn-primary">
                            <?php echo Text::_('TPL_LKIM3_CTA_BTN1'); ?><?php echo $svgArrow; ?>
                        </a>
                        <a href="<?php echo $a($link($this->params->get('ctaLink2'))); ?>" class="btn btn-ghost">
                            <?php echo Text::_('TPL_LKIM3_CTA_BTN2'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($isHome && $this->params->get('showAgencies', 1)) : ?>
        <section class="section tight" aria-label="<?php echo $a(Text::_('TPL_LKIM_AGENCIES')); ?>">
            <div class="wrap">
                <div class="section-head">
                    <div>
                        <div class="mark"><span aria-hidden="true"></span><small><?php echo Text::_('TPL_LKIM3_AGENCY_EYEBROW'); ?></small></div>
                        <h2><?php echo Text::_('TPL_LKIM_AGENCIES'); ?></h2>
                    </div>
                </div>

                <?php if ($fromModules('srcAgencies', 'agencies')) : ?>
                    <jdoc:include type="modules" name="agencies" style="none" />
                <?php else : ?>
                    <div class="agency-carousel">
                        <button class="agency-nav prev" type="button" data-lkim-agency="prev" aria-label="<?php echo $a(Text::_('TPL_LKIM3_PREV')); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6" /></svg>
                        </button>
                        <div class="agency-track" data-lkim-agency-track>
                            <?php for ($pass = 0; $pass < 3; $pass++) : ?>
                                <?php foreach ($agencyLogos as [$file, $label]) : ?>
                                    <div class="agency-logo-item"<?php echo $pass ? ' aria-hidden="true"' : ''; ?>>
                                        <img src="<?php echo $a($img('agencies/' . $file)); ?>" alt="<?php echo $pass ? '' : $a($label); ?>" loading="lazy" decoding="async">
                                    </div>
                                <?php endforeach; ?>
                            <?php endfor; ?>
                        </div>
                        <button class="agency-nav next" type="button" data-lkim-agency="next" aria-label="<?php echo $a(Text::_('TPL_LKIM3_NEXT')); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

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
