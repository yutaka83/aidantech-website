<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.lkimaccessibility
 *
 * @copyright   (C) 2026 Lembaga Kemajuan Ikan Malaysia
 * @license     GNU General Public License version 2 or later
 *
 * Turns the plugin on when it is first installed.
 *
 * Joomla registers a newly installed plugin disabled, which for a single-purpose
 * plugin someone has gone looking for is a trap: the package installs, reports
 * success, and nothing appears on the site. Enabling it here removes a step that
 * is only ever going to be taken anyway.
 *
 * Install only. An update leaves the current state alone, so a plugin somebody
 * deliberately switched off does not come back on behind them.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

class lkimaccessibilityInstallerScript
{
    /**
     * @param   string  $type    install, update or discover_install
     * @param   mixed   $parent  the installer adapter
     *
     * @return  void
     */
    public function postflight($type, $parent)
    {
        if (!\in_array($type, ['install', 'discover_install'], true)) {
            return;
        }

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('lkimaccessibility'));

            $db->setQuery($query)->execute();

            Factory::getApplication()->enqueueMessage(
                Text::_('PLG_SYSTEM_LKIMACCESSIBILITY_ENABLED_ON_INSTALL'),
                'message'
            );
        } catch (\Throwable $e) {
            // Not worth failing an install over — it just means the plugin has
            // to be switched on by hand, which is the normal Joomla behaviour.
            Log::add('plg_system_lkimaccessibility: could not enable itself: ' . $e->getMessage(), Log::WARNING, 'jerror');
        }
    }
}
