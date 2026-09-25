<?php

/**
 * Section: the complaints banner.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

// Its own head rather than partials/head.php: the wording sits inside the
// banner panel, not above it.
$ctaEyebrow = trim((string) $section->get('eyebrow', '')) ?: Text::_('TPL_LKIM3_CTA_EYEBROW');
$ctaHeading = trim((string) $section->get('heading', '')) ?: Text::_('TPL_LKIM3_CTA_TITLE');
$ctaLead    = trim((string) $section->get('lead', '')) ?: Text::_('TPL_LKIM3_CTA_LEAD');
?>
<div class="cta-banner">
    <div class="cta-content">
        <div class="mark"><span aria-hidden="true"></span><small><?php echo $e($ctaEyebrow); ?></small></div>
        <h2><?php echo $e($ctaHeading); ?></h2>
        <p><?php echo $e($ctaLead); ?></p>
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
