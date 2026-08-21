<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.lkim
 *
 * Portal Rasmi Lembaga Kemajuan Ikan Malaysia.
 * Child of Cassiopeia: core assets and html overrides fall back to the parent,
 * this file supplies the government-portal chrome (SPLaSK / MyGovEA elements).
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

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

// The attribute used to mark SPLaSK-audited elements. Configurable because the
// exact vocabulary is set by the prevailing MAMPU/JDN circular.
$splask = $this->params->get('splaskAttribute', 'data-splask') ?: 'data-splask';

// Cassiopeia's compiled CSS is the base; the LKIM layer sits on top of it.
// Both are declared in joomla.asset.json and used by name — registering a
// relative path inline does not resolve for a child template, and the asset
// is then dropped without warning.
$direction = $this->direction === 'rtl' ? 'rtl' : 'ltr';

$wa->usePreset('template.cassiopeia.' . $direction)
    ->useStyle('template.active.language')
    ->useStyle('template.lkim.' . $direction)
    ->useScript('template.lkim');

$wa->registerStyle('template.active', '', [], [], ['template.cassiopeia.' . $direction]);

// Defer Font Awesome so it does not block first paint.
$wa->getAsset('style', 'fontawesome')->setAttribute('rel', 'lazy-stylesheet');

$this->setMetaData('viewport', 'width=device-width, initial-scale=1');

$logoFile = $this->params->get('logoFile');
$jataFile = $this->params->get('jataFile');

if ($logoFile) {
    $logo = '<img class="lkim-logo" src="' . Uri::root(true) . '/' . htmlspecialchars($logoFile, ENT_QUOTES) . '" alt="' . $sitename . '" loading="eager" decoding="async">';
} else {
    $logo = '';
}

$wrapper      = $this->params->get('fluidContainer') ? 'wrapper-fluid' : 'wrapper-static';
$stickyHeader = $this->params->get('stickyHeader') ? ' is-sticky' : '';

$hasClass = '';

if ($this->countModules('sidebar-left', true)) {
    $hasClass .= ' has-sidebar-left';
}

if ($this->countModules('sidebar-right', true)) {
    $hasClass .= ' has-sidebar-right';
}

// Last content change on the site, for the SPLaSK "kemas kini terakhir" stamp.
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
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">

<head>
    <jdoc:include type="metas" />
    <jdoc:include type="styles" />
    <jdoc:include type="scripts" />
</head>

<body id="top" class="site lkim <?php echo $option
    . ' ' . $wrapper
    . ' view-' . $view
    . ($layout ? ' layout-' . $layout : ' no-layout')
    . ($task ? ' task-' . $task : ' no-task')
    . ($itemid ? ' itemid-' . $itemid : '')
    . ($pageclass ? ' ' . $pageclass : '')
    . ($isHome ? ' is-home' : '')
    . $hasClass
    . ($this->direction == 'rtl' ? ' rtl' : '');
?>">

    <nav class="lkim-skiplinks" aria-label="<?php echo Text::_('TPL_LKIM_SKIPLINKS'); ?>">
        <a class="lkim-skiplink" href="#main-content"><?php echo Text::_('TPL_LKIM_SKIP_TO_CONTENT'); ?></a>
        <a class="lkim-skiplink" href="#main-navigation"><?php echo Text::_('TPL_LKIM_SKIP_TO_NAV'); ?></a>
        <a class="lkim-skiplink" href="#site-footer"><?php echo Text::_('TPL_LKIM_SKIP_TO_FOOTER'); ?></a>
    </nav>

    <?php if ($this->params->get('showGovBanner', 1)) : ?>
        <?php
        /*
         * Official government site identification, per JDN / MyGovEA. The copy
         * is theirs verbatim, in both languages.
         *
         * jdn.gov.my injects this with JavaScript into a fixed-position bar;
         * here it is server-rendered in normal flow and uses <details>, so it
         * needs no script, causes no layout shift, and is keyboard-operable
         * on its own.
         */
        ?>
        <section class="lkim-govbar" aria-label="<?php echo Text::_('TPL_LKIM_GOV_BANNER'); ?>" <?php echo $splask; ?>="gov-identification">
            <details class="lkim-shell lkim-govbar-inner">
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

    <header class="lkim-header<?php echo $stickyHeader; ?>">

        <?php if ($this->params->get('showAccessBar', 1)) : ?>
            <div class="lkim-accessbar">
                <div class="lkim-shell lkim-accessbar-inner">
                    <p class="lkim-accessbar-official" <?php echo $splask; ?>="official-notice">
                        <?php echo Text::_('TPL_LKIM_OFFICIAL_NOTICE'); ?>
                    </p>
                    <div class="lkim-accessbar-tools">
                        <div class="lkim-textsize" role="group" aria-label="<?php echo Text::_('TPL_LKIM_TEXTSIZE'); ?>" <?php echo $splask; ?>="text-resize">
                            <button type="button" class="lkim-tsbtn" data-lkim-text="decrease" aria-label="<?php echo Text::_('TPL_LKIM_TEXTSIZE_SMALLER'); ?>">A&minus;</button>
                            <button type="button" class="lkim-tsbtn" data-lkim-text="reset" aria-label="<?php echo Text::_('TPL_LKIM_TEXTSIZE_RESET'); ?>">A</button>
                            <button type="button" class="lkim-tsbtn" data-lkim-text="increase" aria-label="<?php echo Text::_('TPL_LKIM_TEXTSIZE_LARGER'); ?>">A+</button>
                        </div>
                        <button type="button" class="lkim-tsbtn lkim-contrast" data-lkim-contrast aria-pressed="false" <?php echo $splask; ?>="high-contrast">
                            <?php echo Text::_('TPL_LKIM_CONTRAST'); ?>
                        </button>
                        <?php if ($this->countModules('topbar', true)) : ?>
                            <div class="lkim-langswitch" <?php echo $splask; ?>="language-switcher">
                                <jdoc:include type="modules" name="topbar" style="none" />
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="lkim-brandbar">
            <div class="lkim-shell lkim-brandbar-inner">
                <a class="lkim-brand" href="<?php echo $this->baseurl; ?>/">
                    <?php if ($jataFile) : ?>
                        <img class="lkim-jata" src="<?php echo Uri::root(true) . '/' . htmlspecialchars($jataFile, ENT_QUOTES); ?>" alt="" loading="eager" decoding="async">
                    <?php endif; ?>
                    <?php echo $logo; ?>
                    <span class="lkim-brand-text">
                        <span class="lkim-brand-title"><?php echo htmlspecialchars($siteTitle, ENT_COMPAT, 'UTF-8'); ?></span>
                        <?php if ($this->params->get('siteDescription')) : ?>
                            <span class="lkim-brand-tagline"><?php echo htmlspecialchars($this->params->get('siteDescription'), ENT_COMPAT, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
                <?php if ($this->countModules('search', true)) : ?>
                    <div class="lkim-search" <?php echo $splask; ?>="site-search">
                        <jdoc:include type="modules" name="search" style="none" />
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($this->countModules('menu', true)) : ?>
            <div class="lkim-navbar" id="main-navigation">
                <div class="lkim-shell lkim-navbar-inner">
                    <button class="lkim-navtoggle" type="button" data-lkim-navtoggle aria-expanded="false" aria-controls="lkim-mainnav">
                        <span class="lkim-navtoggle-bars" aria-hidden="true"></span>
                        <span><?php echo Text::_('TPL_LKIM_MENU'); ?></span>
                    </button>
                    <nav class="lkim-mainnav" id="lkim-mainnav" aria-label="<?php echo Text::_('TPL_LKIM_MAIN_MENU'); ?>">
                        <jdoc:include type="modules" name="menu" style="none" />
                    </nav>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($this->countModules('below-top', true)) : ?>
            <div class="lkim-shell lkim-below-top">
                <jdoc:include type="modules" name="below-top" style="none" />
            </div>
        <?php endif; ?>
    </header>

    <?php if ($this->countModules('banner', true)) : ?>
        <div class="lkim-banner">
            <jdoc:include type="modules" name="banner" style="none" />
        </div>
    <?php endif; ?>

    <?php if ($this->countModules('quicklinks', true)) : ?>
        <section class="lkim-band lkim-band-quicklinks" aria-label="<?php echo Text::_('TPL_LKIM_QUICKLINKS'); ?>">
            <div class="lkim-shell">
                <jdoc:include type="modules" name="quicklinks" style="none" />
            </div>
        </section>
    <?php endif; ?>

    <?php if ($this->countModules('announcements', true)) : ?>
        <section class="lkim-band lkim-band-announcements">
            <div class="lkim-shell lkim-band-grid">
                <jdoc:include type="modules" name="announcements" style="lkimcard" />
            </div>
        </section>
    <?php endif; ?>

    <main id="main-content" class="lkim-main">
        <div class="lkim-shell lkim-layout<?php echo $hasClass; ?>">

            <?php if ($this->countModules('sidebar-left', true)) : ?>
                <aside class="lkim-sidebar lkim-sidebar-left">
                    <jdoc:include type="modules" name="sidebar-left" style="lkimcard" />
                </aside>
            <?php endif; ?>

            <div class="lkim-content">
                <jdoc:include type="modules" name="breadcrumbs" style="none" />
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

    <?php if ($this->countModules('services', true)) : ?>
        <section class="lkim-band lkim-band-services">
            <div class="lkim-shell lkim-band-grid">
                <jdoc:include type="modules" name="services" style="lkimcard" />
            </div>
        </section>
    <?php endif; ?>

    <?php if ($this->countModules('highlights', true)) : ?>
        <section class="lkim-band lkim-band-highlights">
            <div class="lkim-shell lkim-band-grid">
                <jdoc:include type="modules" name="highlights" style="lkimcard" />
            </div>
        </section>
    <?php endif; ?>

    <?php if ($this->countModules('social', true)) : ?>
        <section class="lkim-band lkim-band-social">
            <div class="lkim-shell lkim-band-grid">
                <jdoc:include type="modules" name="social" style="lkimcard" />
            </div>
        </section>
    <?php endif; ?>

    <?php if ($this->countModules('agencies', true)) : ?>
        <section class="lkim-band lkim-band-agencies" aria-label="<?php echo Text::_('TPL_LKIM_AGENCIES'); ?>">
            <div class="lkim-shell">
                <jdoc:include type="modules" name="agencies" style="none" />
            </div>
        </section>
    <?php endif; ?>

    <footer class="lkim-footer" id="site-footer">
        <?php if ($footerCols) : ?>
            <div class="lkim-shell lkim-footer-cols">
                <?php foreach ($footerCols as $col) : ?>
                    <div class="lkim-footer-col">
                        <jdoc:include type="modules" name="<?php echo $col; ?>" style="lkimfooter" />
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($this->countModules('footer', true)) : ?>
            <div class="lkim-shell lkim-footer-extra">
                <jdoc:include type="modules" name="footer" style="none" />
            </div>
        <?php endif; ?>

        <div class="lkim-footer-bar">
            <div class="lkim-shell lkim-footer-bar-inner">
                <p class="lkim-copyright" <?php echo $splask; ?>="copyright">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteTitle, ENT_COMPAT, 'UTF-8'); ?>. <?php echo Text::_('TPL_LKIM_RIGHTS'); ?>
                </p>
                <p class="lkim-footer-meta">
                    <?php if ($lastUpdate) : ?>
                        <span <?php echo $splask; ?>="last-updated">
                            <?php echo Text::_('TPL_LKIM_LAST_UPDATED'); ?>:
                            <time datetime="<?php echo HTMLHelper::_('date', $lastUpdate, 'Y-m-d'); ?>"><?php echo HTMLHelper::_('date', $lastUpdate, 'd F Y'); ?></time>
                        </span>
                    <?php endif; ?>
                    <?php if ($this->params->get('showVisitorCounter', 1)) : ?>
                        <span <?php echo $splask; ?>="visitor-counter">
                            <?php echo Text::_('TPL_LKIM_VISITORS'); ?>: <span data-lkim-counter>&mdash;</span>
                        </span>
                    <?php endif; ?>
                    <span <?php echo $splask; ?>="best-viewed"><?php echo Text::_('TPL_LKIM_BEST_VIEWED'); ?></span>
                </p>
            </div>
        </div>
    </footer>

    <?php if ($this->params->get('backTop', 1)) : ?>
        <a href="#top" class="lkim-backtotop" aria-label="<?php echo Text::_('TPL_LKIM_BACKTOTOP'); ?>">
            <span aria-hidden="true">&uarr;</span>
        </a>
    <?php endif; ?>

    <jdoc:include type="modules" name="debug" style="none" />
</body>

</html>
