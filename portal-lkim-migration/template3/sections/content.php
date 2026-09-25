<?php

/**
 * Section: the page's own component output, with its sidebars.
 *
 * Self-wrapping — this is the document's <main>, so it carries the skip-link
 * target and cannot be nested inside a generic band element.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

/** @var string $secClass */
/** @var string $secId */
/** @var string $secStyle */
?>
<main id="main-content" class="lkim-main <?php echo $secClass; ?>"<?php echo $secStyle; ?>>
    <div class="wrap lkim-layout<?php echo $hasClass; ?>">

        <?php if ($this->countModules('sidebar-left', true)) : ?>
            <aside class="lkim-sidebar lkim-sidebar-left">
                <jdoc:include type="modules" name="sidebar-left" style="lkimcard" />
            </aside>
        <?php endif; ?>

        <div class="lkim-content">
            <jdoc:include type="modules" name="top-a" style="lkimcard" />
            <jdoc:include type="modules" name="main-top" style="lkimcard" />
            <jdoc:include type="message" />
            <jdoc:include type="component" />
            <jdoc:include type="modules" name="main-bottom" style="lkimcard" />
            <jdoc:include type="modules" name="bottom-a" style="lkimcard" />
        </div>

        <?php if ($this->countModules('sidebar-right', true)) : ?>
            <aside class="lkim-sidebar lkim-sidebar-right">
                <jdoc:include type="modules" name="sidebar-right" style="lkimcard" />
            </aside>
        <?php endif; ?>
    </div>
</main>
