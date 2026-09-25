<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.lkimaccessibility
 *
 * The accessibility panel: disability profiles on top, individual adjustments
 * below.
 *
 * A profile is only a named set of the adjustments underneath it, so choosing
 * one and then changing a single tool behaves the way a visitor expects. Which
 * profiles and tools appear is a plugin setting, because an agency that cannot
 * honour a promise — captions, say — is better off not making it.
 *
 * Rendered by the plugin, which supplies $profiles, $tools, $side, $launcher
 * and $statement.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var array  $profiles */
/** @var array  $tools */
/** @var string $side */
/** @var string $edge */
/** @var bool   $launcher */
/** @var string $statement */
/** @var bool   $speech */

$a = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_COMPAT, 'UTF-8');

$icon = static function (string $path, string $extra = ''): string {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . $extra . '</svg>';
};

// The universal accessibility mark: a figure with arms out, inside a ring.
$markAccess = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
    . '<circle cx="12" cy="12" r="11" fill="none" stroke="currentColor" stroke-width="1.7"/>'
    . '<circle cx="12" cy="6.1" r="1.9" fill="currentColor"/>'
    . '<path d="M4.6 9.2c2.4.9 4.8 1.35 7.4 1.35s5-.45 7.4-1.35M12 10.6V15m0 0 3.1 5.4M12 15l-3.1 5.4" '
    . 'fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"/></svg>';

$profileIcons = [
    'blind'      => $icon('<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><path d="m3 3 18 18"/>'),
    'lowvision'  => $icon('<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>'),
    'colorblind' => $icon('<circle cx="9" cy="12" r="6"/><circle cx="15" cy="12" r="6"/>'),
    'dyslexia'   => $icon('<path d="M4 7h16M4 12h10M4 17h13"/><path d="M17 15.5c1.6.6 3 .2 3 1.2s-1.6 1.4-3 .8"/>'),
    'adhd'       => $icon('<path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1"/><circle cx="12" cy="12" r="3"/>'),
    'epilepsy'   => $icon('<path d="M13 2 4.5 13H11l-1 9 8.5-11H12Z"/>'),
    'motor'      => $icon('<path d="M7 11V5.5a1.5 1.5 0 0 1 3 0V11"/><path d="M10 11V4.5a1.5 1.5 0 0 1 3 0V11"/><path d="M13 11V6a1.5 1.5 0 0 1 3 0v6"/><path d="M16 9.5a1.5 1.5 0 0 1 3 0V15a6 6 0 0 1-6 6h-1a7 7 0 0 1-5-2.1L4 16"/>'),
    'deaf'       => $icon('<path d="M7 9a5 5 0 0 1 9.5-2.2"/><path d="M9 13a3 3 0 0 0 5 2.2c1.4-1.5 2-2.4 3.4-3.2"/><path d="M12 21a2.5 2.5 0 0 0 2.5-2.5"/><path d="m3 3 18 18"/>'),
    'elderly'    => $icon('<circle cx="11" cy="5" r="2.2"/><path d="M11 7.2 9.4 13l2.6 2.4V21"/><path d="M9.4 13 7 21"/><path d="M17 8v13"/>'),
];

$toolIcons = [
    'scale'    => $icon('<path d="M4 20 10.5 4h1L18 20M7 14h8"/>'),
    'line'     => $icon('<path d="M4 6h16M4 12h16M4 18h16"/>'),
    'letter'   => $icon('<path d="M5 18 9 6h1l4 12M6.5 14h6"/><path d="M19 6v12"/>'),
    'font'     => $icon('<path d="M4 18 9 6h1l5 12M6 14h7"/><path d="M17 18h4"/>'),
    'align'    => $icon('<path d="M4 6h16M4 12h10M4 18h13"/>'),
    'links'    => $icon('<path d="M10 13a5 5 0 0 0 7.1 0l2-2a5 5 0 0 0-7.1-7.1L10.8 5"/><path d="M14 11a5 5 0 0 0-7.1 0l-2 2A5 5 0 0 0 12 20.1l1.1-1.1"/>'),
    'headings' => $icon('<path d="M6 4v16M18 4v16M6 12h12"/>'),
    'cursor'   => $icon('<path d="m5 3 14 8-6 1.5 3 7-3 1.5-3-7-5 4Z"/>'),
    'motion'   => $icon('<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/>'),
    'images'   => $icon('<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 16-5-5-5 5-3-3-5 5"/>'),
    'read'     => $icon('<path d="M3 5.5A15 15 0 0 1 12 8a15 15 0 0 1 9-2.5V19a15 15 0 0 0-9 2.5A15 15 0 0 0 3 19Z"/><path d="M12 8v13.5"/>'),
    'guide'    => $icon('<path d="M3 12h18"/><path d="M6 8h12M6 16h12" opacity=".45"/>'),
    'mask'     => $icon('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 14h18"/>'),
    'media'    => $icon('<rect x="2" y="6" width="14" height="12" rx="2"/><path d="m22 8-6 4 6 4Z"/><path d="M5 15h6"/>'),
    'speech'   => $icon('<path d="M11 5 6 9H3v6h3l5 4Z"/><path d="M16 9a4 4 0 0 1 0 6"/><path d="M19 6.5a8 8 0 0 1 0 11"/>'),
    'speak'    => $icon('<rect x="9" y="2.5" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0"/><path d="M12 18v3.5M9 21.5h6"/>'),
    'rate'     => $icon('<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>'),
    'targets'  => $icon('<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/>'),
];

$filterIcons = [
    'contrast' => $icon('<circle cx="12" cy="12" r="9"/><path d="M12 3v18a9 9 0 0 0 0-18Z" fill="currentColor" stroke="none"/>'),
    'invert'   => $icon('<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18Z" fill="currentColor" stroke="none"/><path d="M12 3v18"/>'),
    'mono'     => $icon('<circle cx="12" cy="12" r="9"/><path d="M5 17.5 17.5 5M8 20.5 20.5 8M3.5 14 14 3.5"/>'),
    'lowsat'   => $icon('<path d="M12 3.5c3.5 4 6 7 6 9.7A6 6 0 0 1 6 13.2c0-2.7 2.5-5.7 6-9.7Z"/>'),
    'highsat'  => $icon('<path d="M12 3.5c3.5 4 6 7 6 9.7A6 6 0 0 1 6 13.2c0-2.7 2.5-5.7 6-9.7Z" fill="currentColor" stroke="none"/><path d="M12 3.5c3.5 4 6 7 6 9.7A6 6 0 0 1 6 13.2c0-2.7 2.5-5.7 6-9.7Z"/>'),
];

$filters = ['invert', 'mono', 'lowsat', 'highsat'];

?>
<?php if ($launcher) : ?>
    <button type="button" class="a11y-launcher is-<?php echo $side; ?> is-<?php echo $edge; ?>" aria-expanded="false" aria-controls="a11y-panel"
        aria-label="<?php echo $a(Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_TOOLS')); ?>" title="<?php echo $a(Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_TOOLS')); ?>">
        <?php echo $markAccess; ?>
    </button>
<?php endif; ?>

<div class="a11y-backdrop" hidden></div>

<aside class="a11y-panel is-<?php echo $side; ?>" id="a11y-panel"
    aria-label="<?php echo $a(Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_TOOLS')); ?>"
    data-media-note="<?php echo $a(Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_MEDIA_NOTE')); ?>">

    <div class="a11y-head">
        <h2><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_TOOLS'); ?></h2>
        <button type="button" class="a11y-close" aria-label="<?php echo $a(Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_CLOSE')); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18" /></svg>
        </button>
    </div>

    <div class="a11y-body">
        <?php if ($profiles) : ?>
            <div class="a11y-group">
                <h3><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_PROFILES'); ?></h3>
                <ul class="a11y-grid">
                    <?php foreach ($profiles as $key) : ?>
                        <?php if (!isset($profileIcons[$key])) { continue; } ?>
                        <li>
                            <button type="button" class="a11y-tile" data-a11y-profile="<?php echo $a($key); ?>" aria-pressed="false">
                                <?php echo $profileIcons[$key]; ?>
                                <span><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_P_' . strtoupper($key)); ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="a11y-group">
            <h3><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_TEXT'); ?></h3>
            <ul class="a11y-grid a11y-grid-steps">
                <?php foreach (['scale' => 'PLG_SYSTEM_LKIMACCESSIBILITY_SIZE', 'line' => 'PLG_SYSTEM_LKIMACCESSIBILITY_LINE', 'letter' => 'PLG_SYSTEM_LKIMACCESSIBILITY_LETTER'] as $key => $label) : ?>
                    <li class="a11y-step">
                        <?php echo $toolIcons[$key]; ?>
                        <span class="a11y-step-label"><?php echo Text::_($label); ?></span>
                        <span class="a11y-step-controls">
                            <button type="button" data-a11y-step="<?php echo $key; ?>:down"
                                aria-label="<?php echo $a(Text::sprintf('PLG_SYSTEM_LKIMACCESSIBILITY_DECREASE', Text::_($label))); ?>">&minus;</button>
                            <output data-a11y-out="<?php echo $key; ?>">100%</output>
                            <button type="button" data-a11y-step="<?php echo $key; ?>:up"
                                aria-label="<?php echo $a(Text::sprintf('PLG_SYSTEM_LKIMACCESSIBILITY_INCREASE', Text::_($label))); ?>">+</button>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="a11y-group">
            <h3><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_COLOUR'); ?></h3>
            <ul class="a11y-grid">
                <li>
                    <button type="button" class="a11y-tile" data-a11y-toggle="contrast" aria-pressed="false">
                        <?php echo $filterIcons['contrast']; ?>
                        <span><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_CONTRAST'); ?></span>
                    </button>
                </li>
                <?php foreach ($filters as $f) : ?>
                    <li>
                        <button type="button" class="a11y-tile" data-a11y-filter-btn="<?php echo $f; ?>" aria-pressed="false">
                            <?php echo $filterIcons[$f]; ?>
                            <span><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_F_' . strtoupper($f)); ?></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($tools) : ?>
            <div class="a11y-group">
                <h3><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_NAV'); ?></h3>
                <ul class="a11y-grid">
                    <?php foreach ($tools as $key) : ?>
                        <?php if (!isset($toolIcons[$key])) { continue; } ?>
                        <li>
                            <button type="button" class="a11y-tile" data-a11y-toggle="<?php echo $a($key); ?>" aria-pressed="false">
                                <?php echo $toolIcons[$key]; ?>
                                <span><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_T_' . strtoupper($key)); ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php
        /*
         * Its own group rather than another entry in the tools list. A site that
         * saved its tool selection before this existed would never show a new
         * entry in that list, and reading aloud is too central to arrive only
         * for whoever happens to re-save the plugin.
         *
         * The group is dropped entirely when the browser has no speech
         * synthesis — data-a11y-speechless is set by the script on such a
         * browser, and the stylesheet hides it.
         */
        ?>
        <?php if ($speech) : ?>
            <div class="a11y-group a11y-group-speech">
                <h3><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_SOUND'); ?></h3>
                <ul class="a11y-grid">
                    <li>
                        <button type="button" class="a11y-tile" data-a11y-speak aria-pressed="false">
                            <?php echo $toolIcons['speak']; ?>
                            <span><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_T_SPEAK'); ?></span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="a11y-tile" data-a11y-toggle="speech" aria-pressed="false">
                            <?php echo $toolIcons['speech']; ?>
                            <span><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_T_SPEECH'); ?></span>
                        </button>
                    </li>
                    <li class="a11y-step">
                        <?php echo $toolIcons['rate']; ?>
                        <span class="a11y-step-label"><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_RATE'); ?></span>
                        <span class="a11y-step-controls">
                            <button type="button" data-a11y-step="rate:down"
                                aria-label="<?php echo $a(Text::sprintf('PLG_SYSTEM_LKIMACCESSIBILITY_DECREASE', Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_RATE'))); ?>">&minus;</button>
                            <output data-a11y-out="rate">1.0x</output>
                            <button type="button" data-a11y-step="rate:up"
                                aria-label="<?php echo $a(Text::sprintf('PLG_SYSTEM_LKIMACCESSIBILITY_INCREASE', Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_RATE'))); ?>">+</button>
                        </span>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <div class="a11y-foot">
            <button type="button" class="a11y-reset" data-a11y-reset><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_RESET'); ?></button>
            <?php if ($statement !== '') : ?>
                <a class="a11y-statement" href="<?php echo $a($statement); ?>"><?php echo Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_STATEMENT'); ?></a>
            <?php endif; ?>
        </div>
    </div>
</aside>
