<?php

/**
 * Section: whatever modules sit in a named position.
 *
 * This is the escape hatch for bands the design does not ship. Point a row at a
 * position, publish modules there, and the band renders in the design's own
 * heading and spacing.
 *
 * The position name reaches the page inside a jdoc placeholder, which Joomla
 * resolves against its own list of positions after the template has run, so an
 * unknown name renders nothing rather than erroring.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

/** @var Joomla\Registry\Registry $section */

$position = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $section->get('position', ''));

if ($position === '' || !$this->countModules($position, true)) {
    return;
}

$chrome = (string) $section->get('chrome', 'none') === 'card' ? 'lkimcard' : 'none';

require __DIR__ . '/partials/head.php';
?>
<jdoc:include type="modules" name="<?php echo $position; ?>" style="<?php echo $chrome; ?>" />
