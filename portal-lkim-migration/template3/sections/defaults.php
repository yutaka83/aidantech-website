<?php

/**
 * The design's own page body, as a layout list.
 *
 * Single source of truth for two callers: index.php falls back to it when a
 * style has never been saved, and script.php seeds it into every lkim3 style on
 * install, so the Layout tab opens with the real page in it rather than an
 * empty list to build from scratch.
 *
 * @package  Templates.lkim3
 */

defined('_JEXEC') or die;

return [
    ['type' => 'gateways', 'enabled' => 1, 'spacing' => 'none'],
    ['type' => 'services', 'enabled' => 1, 'anchor' => 'perkhidmatan'],
    ['type' => 'gallery',  'enabled' => 1, 'anchor' => 'media'],
    ['type' => 'news',     'enabled' => 1, 'background' => 'surface'],
    ['type' => 'content',  'enabled' => 1],
    ['type' => 'cta',      'enabled' => 1, 'anchor' => 'aduan'],
    ['type' => 'agencies', 'enabled' => 1],
];
