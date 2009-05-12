<?php
/**
 * DASBiT
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @version $Id$
 */

/**
 * Controller for all IRC related stuff
 */
class DASBiT_Irc_Controller
{
    /**
     * Configuration for the controller
     *
     * @var Zend_Config
     */
    protected $_config;
    
    /**
     * Client for IRC server connection
     *
     * @var DASBiT_Irc_Client
     */
    protected $_client;
    
    /**
     * List of registered plugins
     *
     * @var array
     */
    protected $_plugins = array();
    
    /**
     * List of registered commands
     *
     * @var array
     */
    protected $_commands = array();
    
    /**
     * Create a new controller instance
     *
     * @param Zend_Config $config
     * @param string      $pluginsPath
     */
    public function __construct(Zend_Config $config, $pluginsPath = null)
    {
        // Set configuration
        $this->_config = $config;
        
        // Create server instance
        $this->_client = new DASBiT_Irc_Client($this,
                                               $this->_config->server->hostname,
                                               $this->_config->server->port);
                                               
        // Find and register plugins
        if ($pluginsPath !== null) {
            if (!is_dir($pluginsPath)) {
                throw new DASBiT_Irc_Exception('Plugins path is no directory');
            }
            
            $dir = dir($pluginsPath);
            while (($fileName = $dir->read()) !== false) {
                if (preg_match('#^([A-Za-z0-9]+)\.php$#', $fileName, $match) === 0) {
                    continue;
                }
                
                // Load the plugin
                include $pluginsPath . '/' . $fileName;
                
                $pluginName = $match[1];
                $className  = 'Plugin_' . $pluginName;
                
                if (!class_exists($className, false)) {
                    throw new DASBiT_Irc_Exception('No class with name "' . $className . '" found');
                }

                $plugin = new $className($this);
                
                if (!$plugin instanceof DASBiT_Plugin) {
                    throw new DASBiT_Irc_Exception('Plugin "' . $pluginName . '" does not implement from DASBiT_Plugin');
                }
                
                $this->plugins[$pluginName] = $plugin;
            }
        }
        
        $this->_mainLoop();
    }
       
    /**
     * Get the configuration
     *
     * @return Zend_Config
     */
    public function getConfig()
    {
        return $this->_config;
    }
    
    /**
     * Get the client
     *
     * @return DASBiT_Irc_Client
     */
    public function getClient()
    {
        return $this->_client;
    }
    
    /**
     * Log a message
     *
     * @param  string $message
     * @return void
     */
    public function log($message)
    {
        echo $message . "\n";
    }
    
    /**
     * Register a new command to the controller
     *
     * @param DASBiT_Plugin $plugin
     * @param string        $method
     * @param string        $command
     */
    public function registerCommand(DASBiT_Plugin $plugin, $method, $command)
    {
        $this->_commands[$command] = array($plugin, $method);
    }
    
    /**
     * Main bot loop, will run forever
     *
     * @return void
     */
    protected function _mainLoop()
    {
        while (true) {
            $requests = $this->_client->getRequests();
            
            foreach ($requests as $request) {
                $words   = $request->getWords();
                $command = $words[0];
                
                if ($command[0] === $this->_config->common->prefix) {
                    $command = substr($command, 1);

                    if (isset($this->_commands[$command])) {
                        call_user_func($this->_commands[$command], $request);
                    }
                }
            }
        }
    }
}