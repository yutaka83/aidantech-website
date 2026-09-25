<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.lkim3
 *
 * The template's own maintenance page.
 *
 * Required from index.php before anything else is rendered, and it exits: the
 * component has already run by then, but nothing of it reaches the visitor.
 *
 * Distinct from offline.php, which Joomla renders for its own Site Offline
 * setting in Global Configuration. That one takes the whole site down including
 * the login form; this one keeps the site reachable for anyone logged in, so
 * staff can check the work before turning it off.
 *
 * @var  Joomla\CMS\Document\HtmlDocument  $this
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

$app  = Factory::getApplication();
$root = Uri::root(true);

$heading = $this->params->get('maintenanceHeading') ?: Text::_('TPL_LKIM3_MAINT_HEADING');
$message = $this->params->get('maintenanceMessage') ?: Text::_('TPL_LKIM3_MAINT_MESSAGE');
$until   = trim((string) $this->params->get('maintenanceUntil', ''));
$contact = trim((string) $this->params->get('maintenanceContact', ''));

$logo  = $this->params->get('logoFile', 'media/templates/site/lkim3/images/logo.png');
$image = trim((string) $this->params->get('maintenanceImage', ''));
$e     = static fn($v): string => htmlspecialchars((string) $v, ENT_COMPAT, 'UTF-8');
$a     = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/** A media field can carry Joomla's #joomlaImage adapter fragment; drop it. */
$src = static function (string $path) use ($root): string {
    $path = explode('#', $path, 2)[0];

    return preg_match('#^(https?:)?//|^data:#i', $path)
        ? $path
        : $root . '/' . ltrim($path, '/');
};

/*
 * 503 rather than 200, so a search engine treats this as a temporary outage and
 * keeps the pages it already has. Retry-After only goes out when the "expected
 * back" field parses as a real time — a guess would be worse than silence.
 */
$app->setHeader('Status', '503 Service Unavailable', true);
$app->allowCache(false);

if ($until !== '' && ($stamp = strtotime($until)) !== false && $stamp > time()) {
    $app->setHeader('Retry-After', gmdate('D, d M Y H:i:s', $stamp) . ' GMT', true);
}

$this->setTitle($heading . ' — ' . htmlspecialchars($app->get('sitename'), ENT_QUOTES, 'UTF-8'));
// index.php sets this after the point where maintenance.php returns, and
// offline.php is reached without index.php at all — so both set it here, or a
// phone renders the page at 980px and zooms out.
$this->setMetaData('viewport', 'width=device-width, initial-scale=1');
$this->setMetaData('robots', 'noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">

<head>
    <jdoc:include type="metas" />
    <jdoc:include type="styles" />
</head>

<body class="site lkim lkim3 lk3-maintenance<?php echo $image !== '' ? ' is-image-only' : ''; ?>">
    <?php if ($image !== '') : ?>
        <?php
        /*
         * An image on its own, edge to edge. No logo, no card, no wording —
         * an agency that has had a notice designed wants that notice, not the
         * template's arrangement of it around one.
         *
         * The alt is the heading rather than empty here: this picture is the
         * whole page, so it is the only thing a screen reader or a visitor on
         * a failed image load has to go on.
         */
        ?>
        <main class="lk3-maint-only">
            <img src="<?php echo $a($src($image)); ?>" alt="<?php echo $a($heading); ?>" decoding="async">
        </main>
    <?php else : ?>
    <main class="lk3-maint">
        <div class="lk3-maint-card">
            <?php if ($logo) : ?>
                <img class="lk3-maint-logo" src="<?php echo $a($src($logo)); ?>" alt="" decoding="async">
            <?php endif; ?>

            <p class="lk3-maint-eyebrow"><?php echo Text::_('TPL_LKIM3_MAINT_EYEBROW'); ?></p>
            <h1><?php echo $e($heading); ?></h1>
            <p class="lk3-maint-lead"><?php echo nl2br($e($message)); ?></p>

            <?php if ($until !== '') : ?>
                <p class="lk3-maint-until">
                    <?php echo Text::_('TPL_LKIM3_MAINT_UNTIL'); ?>
                    <strong><?php echo $e($until); ?></strong>
                </p>
            <?php endif; ?>

            <?php if ($contact !== '') : ?>
                <p class="lk3-maint-contact">
                    <?php echo Text::_('TPL_LKIM3_MAINT_CONTACT'); ?>
                    <?php if (str_contains($contact, '@')) : ?>
                        <a href="mailto:<?php echo $a($contact); ?>"><?php echo $e($contact); ?></a>
                    <?php else : ?>
                        <strong><?php echo $e($contact); ?></strong>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </main>
    <?php endif; ?>
</body>

</html>
