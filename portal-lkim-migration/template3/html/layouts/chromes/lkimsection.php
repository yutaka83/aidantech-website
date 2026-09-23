<?php

/**
 * Module chrome: a homepage band.
 *
 * The module title becomes the band heading and the module's content sits
 * beneath it in a grid, matching the "Popular Services" / "News &
 * Announcements" pattern of the 2026 design.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

$module = $displayData['module'];
$params = $displayData['params'];

if (!$module->content) {
    return;
}

$headerTag   = htmlspecialchars($params->get('header_tag', 'h2'), ENT_QUOTES, 'UTF-8');
$moduleClass = trim($params->get('moduleclass_sfx', ''));
?>
<div class="lk-section <?php echo htmlspecialchars($moduleClass, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ((bool) $module->showtitle) : ?>
        <div class="lk-band-head">
            <<?php echo $headerTag; ?> class="lk-band-title"><?php echo $module->title; ?></<?php echo $headerTag; ?>>
        </div>
    <?php endif; ?>
    <div class="lk-section-body">
        <?php echo $module->content; ?>
    </div>
</div>
