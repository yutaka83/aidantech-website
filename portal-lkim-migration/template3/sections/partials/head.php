<?php

/**
 * The design's section heading — eyebrow, title, intro and an optional link —
 * for the section types whose text comes from the row rather than the language
 * files. Renders nothing when the row sets no heading text.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

/** @var Joomla\Registry\Registry $section */

$eyebrow  = trim((string) $section->get('eyebrow', ''));
$heading  = trim((string) $section->get('heading', ''));
$lead     = trim((string) $section->get('lead', ''));
$moreText = trim((string) $section->get('moreText', ''));
$moreLink = trim((string) $section->get('moreLink', ''));

if ($eyebrow === '' && $heading === '' && $lead === '') {
    return;
}
?>
<div class="section-head">
    <div>
        <?php if ($eyebrow !== '') : ?>
            <div class="mark"><span aria-hidden="true"></span><small><?php echo $e($eyebrow); ?></small></div>
        <?php endif; ?>
        <?php if ($heading !== '') : ?>
            <h2><?php echo $e($heading); ?></h2>
        <?php endif; ?>
        <?php if ($lead !== '') : ?>
            <p><?php echo $e($lead); ?></p>
        <?php endif; ?>
    </div>
    <?php if ($moreText !== '' && $moreLink !== '') : ?>
        <a href="<?php echo $a($link($moreLink)); ?>" class="link-more">
            <?php echo $e($moreText); ?><?php echo $svgArrow; ?>
        </a>
    <?php endif; ?>
</div>
