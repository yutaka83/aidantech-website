<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.lkimaccessibility
 *
 * @copyright   (C) 2026 Lembaga Kemajuan Ikan Malaysia
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Aidan\Plugin\System\LkimAccessibility\Extension\LkimAccessibility;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new LkimAccessibility(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('system', 'lkimaccessibility')
                );

                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
