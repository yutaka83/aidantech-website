<?php

/**
 * Section: audience gateway cards.
 *
 * Self-wrapping — it overlaps the bottom of the hero by a negative margin, so
 * it cannot use the standard band wrapper.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var Joomla\Registry\Registry $section */
/** @var string $secClass */
/** @var string $secId */
/** @var string $secStyle */
?>
<div class="wrap quicklinks <?php echo $secClass; ?>"<?php echo $secId . $secStyle; ?>>
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
