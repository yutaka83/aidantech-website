<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.lkim3
 *
 * The LKIM-3 mega menu, built straight from a site menu.
 *
 * The portal's "menu" module position is occupied by a third-party mega menu
 * that renders its own markup, and unpublishing it would change the currently
 * live template. So this template draws its navigation itself, from whichever
 * menu the template style names, and leaves module positions alone. An editor
 * who would rather drive the nav from a module switches the style's
 * "Sumber menu" parameter to "module".
 *
 * The panel layout matches html/mod_menu/default.php:
 *   - a level-2 item with children of its own becomes one column, its title the
 *     heading and its children the links;
 *   - level-2 items without children are gathered into columns of their own,
 *     split evenly so a long list does not run off the panel.
 *
 * Included from index.php, which supplies $app, $this (the document) and $a().
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$menuType = $this->params->get('menuType', 'mainmenu');
$siteMenu = $app->getMenu();
$items    = $siteMenu->getItems('menutype', $menuType) ?: [];

if (!$items) {
    return;
}

$levels   = $app->getIdentity()->getAuthorisedViewLevels();
$tag      = $app->getLanguage()->getTag();
$filter   = $app->getLanguageFilter();
$active   = $siteMenu->getActive();
$path     = $active ? (array) $active->tree : [];
$activeId = $active ? (int) $active->id : 0;
$homeId   = ($home = $siteMenu->getDefault($tag)) ? (int) $home->id : 0;

/** Visible items only, keyed by id, each given a children bucket. */
$byId = [];

foreach ($items as $item) {
    if (!in_array($item->access, $levels)) {
        continue;
    }

    if ($filter && $item->language !== '*' && $item->language !== $tag) {
        continue;
    }

    if (!$item->getParams()->get('menu_show', 1)) {
        continue;
    }

    $item->lk3children = [];
    $byId[(int) $item->id] = $item;
}

// Three tiers is all the panel has room for; anything deeper is dropped.
$tree = [];

foreach ($byId as $item) {
    if ((int) $item->level > 3) {
        continue;
    }

    $parent = (int) $item->parent_id;

    if ((int) $item->level === 1 || !isset($byId[$parent])) {
        $tree[] = $item;
    } else {
        $byId[$parent]->lk3children[] = $item;
    }
}

if (!$tree) {
    return;
}

/** Resolve a menu item to a URL the way mod_menu's helper does. */
$href = static function ($item): string {
    switch ($item->type) {
        case 'separator':
        case 'heading':
            return '';

        case 'url':
            return str_contains($item->link, 'index.php?') && !str_contains($item->link, 'Itemid=')
                ? Route::_($item->link . '&Itemid=' . $item->id)
                : $item->link;

        case 'alias':
            $target = (int) $item->getParams()->get('aliasoptions');

            return $target ? Route::_('index.php?Itemid=' . $target) : '';

        default:
            return Route::_('index.php?Itemid=' . $item->id);
    }
};

$attrs = static function ($item): string {
    $out = '';

    if ((int) $item->browserNav === 1) {
        $out .= ' target="_blank" rel="noopener noreferrer"';
    }

    if ($item->getParams()->get('menu-anchor_title')) {
        $out .= ' title="' . htmlspecialchars($item->getParams()->get('menu-anchor_title'), ENT_QUOTES, 'UTF-8') . '"';
    }

    return $out;
};

/** One anchor, or a plain span for separators and headings. */
$anchor = static function ($item) use ($href, $attrs, $activeId): string {
    $label = htmlspecialchars($item->title, ENT_COMPAT, 'UTF-8');
    $url   = $href($item);

    if ($url === '') {
        return '<span>' . $label . '</span>';
    }

    $current = (int) $item->id === $activeId ? ' aria-current="page"' : '';

    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $current . $attrs($item) . '>' . $label . '</a>';
};

/**
 * Split a top-level item's children into the columns the panel renders.
 *
 * @return  array  list of ['heading' => ?object, 'items' => object[]]
 */
$columns = static function (array $children): array {
    $cols  = [];
    $plain = [];

    foreach ($children as $child) {
        if ($child->lk3children) {
            if ($plain) {
                $cols[] = ['heading' => null, 'items' => $plain];
                $plain  = [];
            }

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

    if ($plain) {
        $cols[] = ['heading' => null, 'items' => $plain];
    }

    return $cols;
};

$itemClass = static function ($item) use ($activeId, $homeId, $path): string {
    $classes = ['nav-item', 'item-' . (int) $item->id];

    if ((int) $item->id === $homeId) {
        $classes[] = 'default';
    }

    if ((int) $item->id === $activeId) {
        $classes[] = 'current';
    }

    if (in_array((int) $item->id, $path, true)) {
        $classes[] = 'active';
    }

    if ($item->type === 'separator') {
        $classes[] = 'divider';
    }

    return implode(' ', $classes);
};
?>
<ul id="mainNav" class="main-nav">
    <?php foreach ($tree as $item) : ?>
        <?php $cols = $item->lk3children ? $columns($item->lk3children) : []; ?>
        <li class="<?php echo $itemClass($item); ?><?php echo $cols ? ' has-mega' : ''; ?>">
            <?php if ($cols) : ?>
                <div class="nav-top">
                    <?php echo $anchor($item); ?>
                    <button class="mega-toggle" type="button" aria-expanded="false"
                        aria-label="<?php echo htmlspecialchars(Text::sprintf('MOD_MENU_TOGGLE_SUBMENU_LABEL', $item->title), ENT_QUOTES, 'UTF-8'); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" /></svg>
                    </button>
                </div>
                <div class="mega<?php echo count($cols) === 1 ? ' mega-narrow' : ''; ?>" style="--mega-cols: <?php echo min(3, count($cols)); ?>">
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
