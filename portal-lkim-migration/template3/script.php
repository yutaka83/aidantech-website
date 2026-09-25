<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.lkim3
 *
 * Seeds the Layout tab so it is never an empty list.
 *
 * A subform parameter has no manifest default — Joomla can only render the rows
 * a style has actually saved. Without this, the first person to open Layout
 * would see nothing and have to rebuild the page the template already renders.
 * So after install, every lkim3 style that has no section list gets the design's
 * own one written into it, from sections/defaults.php.
 *
 * Styles that already have a list are left alone, which makes a reinstall or an
 * update safe.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

class lkim3InstallerScript
{
    /**
     * @param   string  $type    install, update or discover_install
     * @param   mixed   $parent  the installer adapter
     *
     * @return  void
     */
    public function postflight($type, $parent)
    {
        if (!in_array($type, ['install', 'update', 'discover_install'], true)) {
            return;
        }

        $file = JPATH_SITE . '/templates/lkim3/sections/defaults.php';

        if (!is_file($file)) {
            return;
        }

        $defaults = require $file;

        if (!\is_array($defaults) || !$defaults) {
            return;
        }

        // The subform stores its rows keyed sections0, sections1, … in order.
        $rows = [];

        foreach (array_values($defaults) as $i => $row) {
            $rows['sections' . $i] = $row;
        }

        $seeded = 0;

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'params']))
                ->from($db->quoteName('#__template_styles'))
                ->where($db->quoteName('template') . ' = ' . $db->quote('lkim3'))
                ->where($db->quoteName('client_id') . ' = 0');

            foreach ($db->setQuery($query)->loadObjectList() as $style) {
                $params = json_decode((string) $style->params, true) ?: [];

                if (!empty($params['sections'])) {
                    continue;
                }

                $params['sections'] = $rows;

                $encoded = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $update = $db->getQuery(true)
                    ->update($db->quoteName('#__template_styles'))
                    ->set($db->quoteName('params') . ' = ' . $db->quote($encoded))
                    ->where($db->quoteName('id') . ' = ' . (int) $style->id);

                $db->setQuery($update)->execute();
                $seeded++;
            }

            $this->seededCount = $seeded;
        } catch (\Throwable $e) {
            // A template that cannot seed its layout is still a working
            // template — index.php falls back to the same defaults — so this
            // never fails an install. It does have to be loud about it, or a
            // mistake in here looks like the seeding simply having nothing to
            // do.
            Log::add('tpl_lkim3: could not seed the layout list: ' . $e->getMessage(), Log::WARNING, 'jerror');
            Factory::getApplication()->enqueueMessage(
                'tpl_lkim3: the Layout tab could not be pre-filled (' . $e->getMessage() . '). '
                    . 'The template still renders its default sections.',
                'warning'
            );
        }
    }

    /**
     * How many styles this run actually seeded. Exposed so the behaviour can be
     * asserted rather than inferred from a silent success.
     *
     * @var  integer
     */
    public $seededCount = 0;
}
