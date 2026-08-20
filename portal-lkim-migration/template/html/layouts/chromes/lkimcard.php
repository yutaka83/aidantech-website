<?php

/**
 * Module chrome: a titled card used throughout the portal bands.
 *
 * @package  Templates.lkim
 */

defined('_JEXEC') or die;

$module  = $displayData['module'];
$params  = $displayData['params'];
$attribs = $displayData['attribs'];

if (!$module->content) {
    return;
}

$moduleTag   = $params->get('module_tag', 'div');
$headerTag   = htmlspecialchars($params->get('header_tag', 'h2'), ENT_QUOTES, 'UTF-8');
$headerClass = $params->get('header_class', 'lkim-card-title');
$moduleClass = trim($params->get('moduleclass_sfx', ''));
?>
<<?php echo $moduleTag; ?> class="lkim-card <?php echo htmlspecialchars($moduleClass, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ((bool) $module->showtitle) : ?>
        <<?php echo $headerTag; ?> class="<?php echo htmlspecialchars($headerClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $module->title; ?></<?php echo $headerTag; ?>>
    <?php endif; ?>
    <div class="lkim-card-body">
        <?php echo $module->content; ?>
    </div>
</<?php echo $moduleTag; ?>>
