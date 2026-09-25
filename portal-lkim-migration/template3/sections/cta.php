<?php

/**
 * Section: the complaints banner.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
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
