<?php

/**
 * The design's section heading — eyebrow, title, intro and an optional link.
 *
 * A section's own row wins over the design's wording, so any band can be
 * retitled from the Layout tab without touching the language files. A partial
 * passes its own wording in $headDefaults before requiring this; leaving a row
 * field empty falls back to that.
 *
 * Renders nothing when neither the row nor the caller supplies any text.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

/** @var Joomla\Registry\Registry $section */

$headFallback = $headDefaults ?? [];

$headText = static function (string $key) use ($section, $headFallback): string {
    $value = trim((string) $section->get($key, ''));

    return $value !== '' ? $value : trim((string) ($headFallback[$key] ?? ''));
};

$eyebrow  = $headText('eyebrow');
$heading  = $headText('heading');
$lead     = $headText('lead');
$moreText = $headText('moreText');
$moreLink = $headText('moreLink');

// Not left set for whichever partial requires this next.
unset($headDefaults);

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
