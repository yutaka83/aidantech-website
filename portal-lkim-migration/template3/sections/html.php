<?php

/**
 * Section: free HTML entered on the style.
 *
 * Goes out unfiltered, like the Custom Code tab and for the same reason: it is
 * Super User input. Prefer a Custom HTML module and a "modules" section when
 * the content is something an ordinary editor should maintain.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

/** @var Joomla\Registry\Registry $section */

$body = trim((string) $section->get('html', ''));

if ($body === '') {
    return;
}

require __DIR__ . '/partials/head.php';
?>
<div class="lk3-free">
    <?php echo $body; ?>
</div>
