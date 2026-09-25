<?php

/**
 * Section: social space — a horizontally scrolling gallery strip.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

// Its own head rather than partials/head.php, because the social icons sit
// where the "more" link goes on every other band.
$galEyebrow = trim((string) $section->get('eyebrow', '')) ?: Text::_('TPL_LKIM3_GAL_EYEBROW');
$galHeading = trim((string) $section->get('heading', '')) ?: Text::_('TPL_LKIM3_GAL_TITLE');
$galLead    = trim((string) $section->get('lead', ''));
?>
<div class="section-head">
    <div>
        <div class="mark"><span aria-hidden="true"></span><small><?php echo $e($galEyebrow); ?></small></div>
        <h2><?php echo $e($galHeading); ?></h2>
        <?php if ($galLead !== '') : ?>
            <p><?php echo $e($galLead); ?></p>
        <?php endif; ?>
    </div>
    <?php if ($socials) : ?>
        <div class="gallery-tabs">
            <?php foreach ($socials as $key => $url) : ?>
                <a href="<?php echo $a($url); ?>" rel="noopener noreferrer" target="_blank"
                    aria-label="<?php echo $a($socialLabel[$key]); ?>" title="<?php echo $a($socialLabel[$key]); ?>">
                    <?php echo $svgSocial[$key]; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($fromModules('srcGallery', 'highlights')) : ?>
    <jdoc:include type="modules" name="highlights" style="none" />
<?php else : ?>
    <div class="gallery-strip">
        <?php foreach ($gallery as [$file, $label]) : ?>
            <figure class="g-item">
                <img src="<?php echo $a($img($file)); ?>" alt="<?php echo $a(Text::_($label)); ?>" loading="lazy" decoding="async">
                <figcaption class="g-caption"><?php echo Text::_($label); ?></figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
