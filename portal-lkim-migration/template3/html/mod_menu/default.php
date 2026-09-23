<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.lkim3
 *
 * Mega-menu layout for the LKIM-3 header.
 *
 * Core's layout emits a flat <ul> with nested <ul>s, which the design cannot
 * style into columns. This one rebuilds the flat $list into a tree and renders
 * the panel the design asks for:
 *
 *   - A level-2 item that has children of its own becomes one column, its title
 *     the column heading and its children the links.
 *   - Level-2 items with no children of their own are gathered into columns of
 *     their own, split evenly so a long list does not run off the panel.
 *
 * Anything deeper than level 3 is folded into its level-3 parent's column, since
 * the panel has nowhere to put a third tier.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Filter\OutputFilter;

/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $app->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('mod_menu');

$tagId      = $params->get('tag_id', '') ?: 'mainNav';
$startLevel = (int) $params->get('startLevel', 1);

// Rebuild the tree. $list is depth-first, so a stack keyed by level is enough.
$tree   = [];
$byId   = [];

foreach ($list as $item) {
    $item->lk3children = [];
    $byId[$item->id]   = $item;

    if ((int) $item->level === $startLevel || !isset($byId[$item->parent_id])) {
        $tree[] = $item;
    } else {
        $byId[$item->parent_id]->lk3children[] = $item;
    }
}

/**
 * Render one anchor, reusing core's per-type layouts so menu icons, images,
 * target windows and aria-current keep working.
 */
$anchor = function ($item) use ($params, $active_id, $default_id, $path) {
    $itemParams = $item->getParams();

    ob_start();

    switch ($item->type) {
        case 'separator':
        case 'component':
        case 'heading':
        case 'url':
            require \Joomla\CMS\Helper\ModuleHelper::getLayoutPath('mod_menu', 'default_' . $item->type);
            break;

        default:
            require \Joomla\CMS\Helper\ModuleHelper::getLayoutPath('mod_menu', 'default_url');
            break;
    }

    return ob_get_clean();
};

/**
 * Split the level-2 items into the columns the mega panel renders.
 *
 * @return  array  list of ['heading' => ?object, 'items' => object[]]
 */
$columns = function (array $children): array {
    $cols  = [];
    $plain = [];

    $flush = function () use (&$cols, &$plain) {
        if (!$plain) {
            return;
        }

        $cols[] = ['heading' => null, 'items' => $plain];
        $plain  = [];
    };

    foreach ($children as $child) {
        if ($child->lk3children) {
            $flush();
            $cols[] = ['heading' => $child, 'items' => $child->lk3children];
        } else {
            $plain[] = $child;
        }
    }

    // Nothing had children of its own: spread the flat list over up to three
    // columns rather than one very long one.
    if (!$cols && $plain) {
        $count = min(3, max(1, (int) ceil(count($plain) / 6)));

        foreach (array_chunk($plain, (int) ceil(count($plain) / $count)) as $chunk) {
            $cols[] = ['heading' => null, 'items' => $chunk];
        }

        return $cols;
    }

    $flush();

    return $cols;
};

$itemClass = function ($item) use ($active_id, $default_id, $path): string {
    $classes    = ['nav-item', 'item-' . $item->id];
    $itemParams = $item->getParams();

    if ($item->id == $default_id) {
        $classes[] = 'default';
    }

    if ($item->id == $active_id || ($item->type === 'alias' && $itemParams->get('aliasoptions') == $active_id)) {
        $classes[] = 'current';
    }

    if (in_array($item->id, $path)) {
        $classes[] = 'active';
    }

    if ($item->type === 'separator') {
        $classes[] = 'divider';
    }

    return implode(' ', $classes);
};
?>
<ul id="<?php echo htmlspecialchars($tagId, ENT_QUOTES, 'UTF-8'); ?>" class="main-nav mod-menu <?php echo $class_sfx; ?>">
    <?php foreach ($tree as $item) : ?>
        <?php $cols = $item->lk3children ? $columns($item->lk3children) : []; ?>
        <li class="<?php echo $itemClass($item); ?><?php echo $cols ? ' has-mega' : ''; ?>">
            <?php if ($cols) : ?>
                <div class="nav-top">
                    <?php echo $anchor($item); ?>
                    <button class="mega-toggle" type="button" data-lkim-mega aria-expanded="false"
                        aria-label="<?php echo htmlspecialchars(Text::sprintf('MOD_MENU_TOGGLE_SUBMENU_LABEL', $item->title), ENT_QUOTES, 'UTF-8'); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" /></svg>
                    </button>
                </div>
                <div class="mega<?php echo count($cols) === 1 ? ' mega-narrow' : ''; ?>"
                    style="--mega-cols: <?php echo min(3, count($cols)); ?>">
                    <?php foreach ($cols as $col) : ?>
                        <div class="mega-col">
                            <?php if ($col['heading']) : ?>
                                <h4><?php echo $anchor($col['heading']); ?></h4>
                            <?php endif; ?>
                            <ul>
                                <?php foreach ($col['items'] as $leaf) : ?>
                                    <li class="<?php echo $itemClass($leaf); ?>"><?php echo $anchor($leaf); ?></li>
                                    <?php foreach ($leaf->lk3children as $deep) : ?>
                                        <li class="<?php echo $itemClass($deep); ?> is-deep"><?php echo $anchor($deep); ?></li>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <?php echo $anchor($item); ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
