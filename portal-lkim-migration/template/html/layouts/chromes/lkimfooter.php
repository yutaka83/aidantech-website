<?php

/**
 * Module chrome: a plain footer column - underlined heading, no card frame.
 *
 * @package  Templates.lkim
 */

defined('_JEXEC') or die;

$module = $displayData['module'];
$params = $displayData['params'];

if (!$module->content) {
    return;
}

$headerTag = htmlspecialchars($params->get('header_tag', 'h3'), ENT_QUOTES, 'UTF-8');
?>
<?php if ((bool) $module->showtitle) : ?>
    <<?php echo $headerTag; ?> class="lkim-footer-title"><?php echo $module->title; ?></<?php echo $headerTag; ?>>
<?php endif; ?>
<?php echo $module->content; ?>
