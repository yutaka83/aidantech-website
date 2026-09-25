<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.lkim3
 *
 * Joomla's own Site Offline page, in the portal's clothes.
 *
 * Joomla renders this when Global Configuration > Site > Site Offline is on.
 * That setting takes the whole site down, login form included, which is why it
 * carries one — unlike the template's own maintenance mode, which leaves the
 * site reachable for anyone already signed in.
 *
 * @var  Joomla\CMS\Document\HtmlDocument  $this
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$app  = Factory::getApplication();
$root = Uri::root(true);
$logo  = $this->params->get('logoFile', 'media/templates/site/lkim3/images/logo.png');
$image = trim((string) $this->params->get('maintenanceImage', ''));
$a     = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/** A media field can carry Joomla's #joomlaImage adapter fragment; drop it. */
$src = static function (string $path) use ($root): string {
    $path = explode('#', $path, 2)[0];

    return preg_match('#^(https?:)?//|^data:#i', $path)
        ? $path
        : $root . '/' . ltrim($path, '/');
};

// index.php sets this after the point where maintenance.php returns, and
// offline.php is reached without index.php at all — so both set it here, or a
// phone renders the page at 980px and zooms out.
$this->setMetaData('viewport', 'width=device-width, initial-scale=1');
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">

<head>
    <jdoc:include type="metas" />
    <jdoc:include type="styles" />
    <jdoc:include type="scripts" />
</head>

<body class="site lkim lkim3 lk3-maintenance">
    <main class="lk3-maint">
        <div class="lk3-maint-card">
            <?php if ($logo) : ?>
                <img class="lk3-maint-logo" src="<?php echo $a($src($logo)); ?>" alt="" decoding="async">
            <?php endif; ?>

            <?php if ($image !== '') : ?>
                <?php // alt="" on purpose: the message below carries the meaning. ?>
                <img class="lk3-maint-image" src="<?php echo $a($src($image)); ?>" alt="" decoding="async">
            <?php endif; ?>

            <p class="lk3-maint-eyebrow"><?php echo Text::_('TPL_LKIM3_MAINT_EYEBROW'); ?></p>

            <?php if ($app->get('display_offline_message', 1) == 1 && str_replace(' ', '', $app->get('offline_message')) != '') : ?>
                <h1><?php echo $app->get('offline_message'); ?></h1>
            <?php elseif ($app->get('display_offline_message', 1) == 2) : ?>
                <h1><?php echo Text::_('JOFFLINE_MESSAGE'); ?></h1>
            <?php else : ?>
                <h1><?php echo Text::_('TPL_LKIM3_MAINT_HEADING'); ?></h1>
            <?php endif; ?>

            <jdoc:include type="message" />

            <form action="<?php echo Route::_('index.php', true); ?>" method="post" class="lk3-maint-login">
                <div class="lk3-maint-field">
                    <label for="username"><?php echo Text::_('JGLOBAL_USERNAME'); ?></label>
                    <input name="username" id="username" type="text" autocomplete="username" required>
                </div>
                <div class="lk3-maint-field">
                    <label for="passwd"><?php echo Text::_('JGLOBAL_PASSWORD'); ?></label>
                    <input name="password" id="passwd" type="password" autocomplete="current-password" required>
                </div>

                <?php if (count($twofactormethods ?? []) > 1) : ?>
                    <div class="lk3-maint-field">
                        <label for="secretkey"><?php echo Text::_('JGLOBAL_SECRETKEY'); ?></label>
                        <input name="secretkey" id="secretkey" type="text" autocomplete="one-time-code">
                    </div>
                <?php endif; ?>

                <button type="submit" name="Submit" class="btn btn-primary"><?php echo Text::_('JLOGIN'); ?></button>

                <input type="hidden" name="option" value="com_users">
                <input type="hidden" name="task" value="user.login">
                <input type="hidden" name="return" value="<?php echo base64_encode(Uri::base()); ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </div>
    </main>

    <jdoc:include type="modules" name="debug" style="none" />
</body>

</html>
