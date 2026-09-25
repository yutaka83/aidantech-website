<?php

/**
 * Section: the related-agency logo carousel.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$headDefaults = [
    'eyebrow' => Text::_('TPL_LKIM3_AGENCY_EYEBROW'),
    'heading' => Text::_('TPL_LKIM_AGENCIES'),
];

require __DIR__ . '/partials/head.php';
?>

<?php if ($fromModules('srcAgencies', 'agencies')) : ?>
    <jdoc:include type="modules" name="agencies" style="none" />
<?php else : ?>
    <div class="agency-carousel">
        <button class="agency-nav prev" type="button" data-lkim-agency="prev" aria-label="<?php echo $a(Text::_('TPL_LKIM3_PREV')); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6" /></svg>
        </button>
        <div class="agency-track" data-lkim-agency-track>
            <?php for ($pass = 0; $pass < 3; $pass++) : ?>
                <?php foreach ($agencyLogos as [$file, $label]) : ?>
                    <div class="agency-logo-item"<?php echo $pass ? ' aria-hidden="true"' : ''; ?>>
                        <img src="<?php echo $a($img('agencies/' . $file)); ?>" alt="<?php echo $pass ? '' : $a($label); ?>" loading="lazy" decoding="async">
                    </div>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
        <button class="agency-nav next" type="button" data-lkim-agency="next" aria-label="<?php echo $a(Text::_('TPL_LKIM3_NEXT')); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6" /></svg>
        </button>
    </div>
<?php endif; ?>
