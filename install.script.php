<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * Install script file for the Iprestrict plugins and modules
 */
class iprestrictInstallerScript
{

    /**
     * method to run after an install/update/uninstall method
     *
     * @return void
     */
    function postflight($type, $parent)
    {
        if ($type == 'install' || $type == 'update') {
            $this->installPlugins();
        }
    }

    /**
     *  method to install the Iprestrict plugins
     *
     * @return void
     */
    function installPlugins()
    {
        // Install plugins from tmp path
        $pluginsDir = JPATH_ROOT . '/modules/mod_menuiprestrict/plugins/';

        var_dump($pluginsDir);

        if (!file_exists($pluginsDir)) {
            $pluginsDir = JPATH_ROOT . '../build' . JURI::root(true) . '/plugins/';
        }

        // List of iprestrict plugins to install
        $plugins = [
            'content/iprestrict',
            'search/contentiprestrict',
            'system/iprestrict'
        ];

        foreach ($plugins as $plugin) {
            $path = $pluginsDir . $plugin;

            $installer = new JInstaller();
            $installer->update($path);
        }
    }
}
