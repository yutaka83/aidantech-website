<?php

/**
 * Section: services — one feature card plus four supporting cards.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
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
