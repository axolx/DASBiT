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
 * @author  $Author$
 * @version $Revision$
 */

/**
 * @see DASBiT_Controller_Dispatcher
 */
require_once 'DASBiT/Controller/Dispatcher.php';

/**
 * Front controller which handles the main loop
 */
class DASBiT_Controller_Front
{
    /**
     * Singleton instance of self
     *
     * @var DASBiT_Controller_Front
     */
    protected static $_instance = null;
    
    /**
     * Server to connect to
     *
     * @var string
     */
    protected $_server;
    
    /**
     * Nickname of the bot in IRC
     *
     * @var string
     */
    protected $_nickname = 'DASBiT';
    
    /**
     * Username of the bot in IRC
     *
     * @var string
     */
    protected $_username = 'dasbit@dasprids.de';
    
    /**
     * Dispatcher to use 
     *
     * @var DASBiT_Controller_Dispatcher
     */
    protected $_dispatcher;
    
    /**
     * Wether the dispatch is running or not
     *
     * @var boolean
     */
    protected $_dispatchRunning = false;
    
    /**
     * List of all controllers
     *
     * @var array
     */
    protected $_controllers = array();
    
    /**
     * Logger to log messages to
     *
     * @var DASBiT_Log_Interface
     */
    protected $_logger;
    
    /**
     * Creates the singleton instance.
     * Checks the basic setup and prepares the dispatch loop
     */
    protected function __construct()
    {
        // Check if somebody ran this via browser
        if (isset($_SERVER['HTTP_HOST']) === true) {
            require_once 'DASBiT/Controller/Exception.php';
            throw new DASBiT_Controller_Exception('DASBiT may not be run via a browser');
        }

        // This thing should run very long
        set_time_limit(0);
        
        // Instantiate the dispatcher
        $this->_dispatcher = new DASBiT_Controller_Dispatcher();
    }
       
    /**
     * Get the singleton instance
     * 
     * @return DASBiT_Controller_Front
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        
        return self::$_instance;
    }
    
    /**
     * Set the controller directory
     *
     * @param  string $controllerDirectory The directory which contains the controllers
     * @throws DASBiT_Controller_Exception When controller directory does not exist
     * @throws DASBiT_Controller_Exception When a controller name is invalid
     * @throws DASBiT_Controller_Exception When a controller is not readable
     * @throws DASBiT_Controller_Exception When a controller class does not exist
     * @return DASBiT_Controller_Front
     */
    public function setControllerDirectory($controllerDirectory)
    {
        if (is_dir($applicationPath) === false) {
            require_once 'DASBiT/Controller/Exception.php';
            throw new DASBiT_Controller_Exception('Controller directory does not exist'); 
        }
        
        $dir = dir($controllerDirectory . '/controllers');
        while (($file = $dir->read()) !== false) {
            $controllerPath = $controllerDirectory . '/controllers/' . $file;
            $split          = explode('.', $file);
            $extension      = array_pop($split);
            
            if ($file === '.' or $file === '..' or
                is_dir($controllerPath) === true or
                $extension !== 'php') {
                continue; 
            }
            
            if (count($split) !== 1) {
                require_once 'DASBiT/Controller/Exception.php';
                throw new DASBiT_Controller_Exception('Controller name is invalid (' . $file . ')'); 
            }
           
            if (is_readable($controllerPath) === false) {
                require_once 'DASBiT/Controller/Exception.php';
                throw new DASBiT_Controller_Exception('Controller directory contains unreadable controller (' . $file . ')');                
            }

            // Directly require it, we may use it surely
            $controllerName = $split[0] . '_Controller';
            
            require_once $controllerPath;
            
            if (class_exists($controllerName) === false) {
                require_once 'DASBiT/Controller/Exception.php';
                throw new DASBiT_Controller_Exception('Controller class does not exist (' . $controllerName . ')');
            }
            
            $this->_controllers[] = new $controllerName;
        }
        
        return $this;
    }
    
    /**
     * Set the nickname of the bot
     *
     * @param  string $nickname The nickname of the bot
     * @throws InvalidArgumentException When nickname is no string
     * @return DASBiT_Controller_Front
     */
    public function setNickname($nickname)
    {
        if (is_string($nickname) === false) {
            throw new InvalidArgumentException('Nickname must be a string'); 
        }
        
        $this->_nickname = $nickname;
        
        return $this;
    }
    
    /**
     * Get the nickname
     *
     * @return string
     */
    public function getNickname()
    {
        return $this->_nickname;
    }
    
    /**
     * Set the username of the bot
     *
     * @param  string $username The username of the bot
     * @throws InvalidArgumentException When username is no string
     * @return DASBiT_Controller_Front
     */
    public function setUsername($username)
    {
        if (is_string($username) === false) {
            throw new InvalidArgumentException('Username must be a string'); 
        }
        
        $this->_username = $username;
        
        return $this;
    }
    
    /**
     * Get the username
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->_username;
    }
    
    /**
     * Set the logger to use
     * 
     * @param  DASBiT_Log_Interface $logger The logger to use
     * @return DASBiT_Controller_Front
     */
    public function setLogger(DASBiT_Log_Interface $logger)
    {
        $this->_logger = $logger;
    }
    
    /**
     * Get the current logger
     *
     * @return DASBiT_Log_Interface
     */
    public function getLogger()
    {
        if ($this->_logger === null) {
            require_once 'DASBiT/Log/Stdout.php';
            $this->_logger = new DASBiT_Log_Stdout();
        }

        return $this->_logger;
    }
    
    /**
     * Start the dispatch loop
     *
     * @param  string $server The server to connect to
     * @throws DASBiT_Controller_Exception When a dispatch is already running
     * @throws DASBiT_Controller_Exception When the server is not a string
     * @return void
     */
    public function dispatch($server)
    {       
        if ($this->_dispatchRunning === true) {
            require_once 'DASBiT/Controller/Exception.php';
            throw new DASBiT_Controller_Exception('A dispatch is already running');        
        }
        
        $this->_dispatchRunning = true;
        
        if (is_string($server) === false) {
            require_once 'DASBiT/Controller/Exception.php';
            throw new DASBiT_Controller_Exception('Server must be a string');        
        }
               
        // Start the dispatch loop
        $this->_dispatcher->setServer($server)
                          ->dispatchLoop();
    }
    
    /**
     * Disallow cloning of front controller
     * 
     * @throws DASBiT_Controller_Exception As cloning is not allowed
     * @return void
     */
    public function __clone()
    {
        require_once 'DASBiT/Controller/Exception.php';
        throw new DASBiT_Controller_Exception('Cloning of front controller is not allowed');
    }
}