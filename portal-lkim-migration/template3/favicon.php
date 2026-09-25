<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.lkim3
 *
 * The favicon link, from the style.
 *
 * Its own file because three pages need it and none of them shares a code path:
 * index.php for the site, offline.php which Joomla renders without index.php at
 * all, and maintenance.php, which is required from index.php early enough to
 * inherit this.
 *
 * Joomla otherwise falls back to a favicon.ico in the template or site root, so
 * an empty parameter is a working state, not a missing one.
 *
 * @var  Joomla\CMS\Document\HtmlDocument  $this
 */

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

$faviconFile = trim((string) $this->params->get('faviconFile', ''));

if ($faviconFile !== '') {
    // A media field appends Joomla's #joomlaImage adapter fragment to the path.
    $faviconFile = explode('#', $faviconFile, 2)[0];

    $faviconUrl = preg_match('#^(https?:)?//|^data:#i', $faviconFile)
        ? $faviconFile
        : Uri::root(true) . '/' . ltrim($faviconFile, '/');

    $faviconType = [
        'ico'  => 'image/x-icon',
        'png'  => 'image/png',
        'svg'  => 'image/svg+xml',
        'gif'  => 'image/gif',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ][strtolower(pathinfo(parse_url($faviconUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION))] ?? 'image/x-icon';

    /*
     * Just the one relation. An apple-touch-icon pointing at the same file
     * cannot go through here — addHeadLink keys its links by href, so a second
     * relation on one URL silently replaces the first — and addCustomTag only
     * reaches pages that include the scripts block, which the maintenance page
     * deliberately does not. An Apple icon also wants its own square 180px
     * artwork without transparency rather than a reused favicon, so it belongs
     * in a field of its own if it is ever wanted.
     */
    $this->addHeadLink($faviconUrl, 'icon', 'rel', ['type' => $faviconType]);
}
