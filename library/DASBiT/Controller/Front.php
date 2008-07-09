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
 * @see DASBiT_Controller_Plugin_Broker
 */
require_once 'DASBiT/Controller/Plugin/Broker.php';

/**
 * @see DASBiT_Controller_Response
 */
require_once 'DASBiT/Controller/Response.php';

/**
 * @see DASBiT_Controller_Request
 */
require_once 'DASBiT/Controller/Request.php';

/**
 * @see DASBiT_Irc_Channel
 */
require_once 'DASBiT/Irc/Channel.php';

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
     * Address of the server
     *
     * @var string
     */
    protected $_address = null;
    
    /**
     * Port of the server
     *
     * @var integer
     */
    protected $_port = null;
    
    /**
     * Servername to respond to in case
     *
     * @var string
     */
    protected $_serverName = null;
    
    /**
     * Socket to the IRC server
     *
     * @var resource
     */
    protected $_socket;
    
    /**
     * When the last pong was received
     *
     * @var integer
     */
    protected $_lastPongTime;
    
   /**
     * Wether nickname is in use or not
     *
     * @var boolean
     */
    protected $_nicknameInUse = false;
    
    /**
     * Current nickname
     * 
     * @var string
     */
    protected $_currentNickname;
    
    /**
     * Alternative nickname
     *
     * @var string
     */
    protected $_alternateNickname = null;
    
    /**
     * Wether we are connected or not
     *
     * @var boolean
     */
    protected $_connected = false;
    
    /**
     * Channels which the bot is connected to
     *
     * @var array
     */
    protected $_channels = array();
    
    /**
     * Time for delayed checks 
     *
     * @var integer
     */
    protected $_delayTime;
    
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
     * Plugin broker
     *
     * @var DASBiT_Controller_Plugin_Broker
     */
    protected $_pluginBroker;
    
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
        
        // Instantiate the plugin broker
        $this->_pluginBroker = new DASBiT_Controller_Plugin_Broker();
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
        if (is_dir($controllerDirectory) === false) {
            require_once 'DASBiT/Controller/Exception.php';
            throw new DASBiT_Controller_Exception('Controller directory does not exist'); 
        }
        
        $dir = dir($controllerDirectory);
        while (($file = $dir->read()) !== false) {
            $controllerPath = $controllerDirectory . '/' . $file;
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
            $controllerName = strtolower($split[0]);
            $className      = $split[0] . '_Controller';
            
            require_once $controllerPath;
            
            if (class_exists($className) === false) {
                require_once 'DASBiT/Controller/Exception.php';
                throw new DASBiT_Controller_Exception('Controller class does not exist (' . $className . ')');
            }
            
            $controller = new $className;
            if (($controller instanceof DASBiT_Controller_Action) === false) {
                require_once 'DASBiT/Controller/Exception.php';
                throw new DASBiT_Controller_Exception('Controller class does not inherit DASBiT_Controller_Action (' . $className . ')');                
            }
            
            $this->_controllers[$controllerName] = $controller;
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
        
        if ($this->_connected === true) {
            $this->_sendNickname();
        }
        
        return $this;
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
     * Register a plugin with the plugin broker
     *
     * @param  DASBiT_Controller_Plugin_Abstract $plugin
     * @return DASBiT_Controller_Front
     */
    public function registerPlugin(DASBiT_Controller_Plugin_Abstract $plugin)
    {
        $this->_pluginBroker->registerPlugin($plugin);
        
        return $this;
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
            throw new DASBiT_Controller_Exception('A dispatch loop is already running');        
        }
        
        $this->_dispatchRunning = true;
               
        // Start the dispatch loop
        $this->_setServer($server);
        $this->_dispatchLoop();
    }
    
    /**
     * Main dispatch loop
     * 
     * This one while run *forever*, so don't plan to do stuff after calling this
     *
     * @return void
     */
    protected function _dispatchLoop()
    {
        $this->_connect();

        $buffer = '';        
        while (true) {
            $this->_delayedChecks();
            
            $select = socket_select($read = array($this->_socket), $write, $except = null, 1);
            if ($select !== 0) {
                if ($select !== false) {
                    $data = socket_read($this->_socket, 10240);
                }
                
                if ($select !== false and $data !== false) {
                    $buffer .= $data;
                    
                    $lineBreak = strpos($buffer, "\n");
                    while ($lineBreak !== false) {
                        $line = substr($buffer, 0, $lineBreak);
    
                        if (strlen($line) > 0) {
                            $this->_dispatchLine(trim($line));
                        }
    
                        $buffer    = substr($buffer, $lineBreak + 1);
                        $lineBreak = strpos($buffer, "\n");
                    }
                } else {
                    $errorString = socket_strerror(socket_last_error($this->_socket));
                    $this->getLogger()->log('Connection to server lost, reconnecting in one minute. Reason: ' . $errorString);
                    sleep(60);
    
                    $this->_connect();
                }
            }
        }
    }
    
    /**
     * Run delayed checks, like regain of original nick or check of lag time
     *
     * @return void 
     */
    protected function _delayedChecks()
    {
        if ($this->_connected === true) {
            if ($this->_delayTime + 5 > time()) {
                return;
            }
            
            if ($this->_lastPongTime + 60 <= time()) {
                $this->getLogger()->log('Maximum lag reached, reconnecting');
                
                $this->_connect();
            }
            
            if ($this->_nicknameInUse === true) {
                $this->getLogger()->log('Trying to regain original nick');
                
                $this->_sendNickname();
            }
            
            $response = DASBiT_Controller_Response::getInstance();
            $response->sendRaw('PING ' . $this->_serverName);
        }
    }
    
    /**
     * Dispatch a line from the server
     *
     * @param string $line
     */
    protected function _dispatchLine($line)
    {
        $words    = explode(' ', $line);
        $response = DASBiT_Controller_Response::getInstance();
        
        // Respond to ping
        if ($words[0] === 'PING' and isset($words[1]) === true) {
            $response->sendRaw('PONG ' . $words[1]);
            return;
        }
        
        // If there are no further arguments, ignore it
        if (isset($words[1]) === false) {
            return;
        }
        
        // See if there is a response code
        if (is_numeric(($words[1]))) {
            $responseCode = (int) $words[1];
            
            if ($responseCode > 400) {
                $this->_dispatchErrorReply($responseCode, $line, $words);
            } else {
                $this->_dispatchCommandReply($responseCode, $line, $words);
            }
        } else if ($words[1] === 'PRIVMSG') {
            $this->_dispatchPrivMsg($line, $words);
        } else {
            // Else handle the command
            $this->_dispatchCommand($words[1], $line, $words);
        }
    }    
   
    /**
     * Dispatch a private message
     *
     * @param string $line  The string frm the server
     * @param array  $words The string splitted into words
     */
    protected function _dispatchPrivMsg($line, array $words)
    {
        // See what this is
        if (count($words) > 4 and $words[2] === $this->_currentNickname and
            strpos($words[3], chr(1) !== false)) {
            // Looks like a CTCP command
            if (preg_match('#^.*' . chr(1) . '([^ ]+)(.*)' . chr(1) . '.*$#', $line, $match) === 1) {
                $ctcpCommand = strtoupper($matches[1]);
                // @todo Handle some CTCP commands   
            }
        } else {
            // This is a default message
            $request = new DASBiT_Controller_Request($line, $this->_currentNickname);
            
            // Call pre dispatch plugins
            $this->_pluginBroker->preDispatch($request);
            
            // @todo Call controllers with the request
            
            // Call post dispatch plugins
            $this->_pluginBroker->postDispatch($request);
        }
    }

    /**
     * Dispatch a command reply from the server
     *
     * @param  string $command The command
     * @param  string $line    The string from the server
     * @param  array  $words   The string splitted into words
     * @return void
     */
    protected function _dispatchCommand($command, $line, array $words)
    {
        switch ($command) {
            case 'NICK':
                if ($this->_nicknameInUse === true and
                    preg_match('#:' . $this->_alternateNickname
                               . '[^ ]+ NICK :'
                               . $this->_nickname
                               . '#',
                               $line) === 1) {
                    $this->getLogger()->log('Original nickname regained');
                    
                    $this->_nicknameInUse     = false;
                    $this->_alternateNickname = null;
                    $this->_currentNickname   = $this->_nickname;
                }
                break;
                
            case 'PONG':
                $this->_lastPongTime = time();
                break;

            case 'KICK':
                if ($words[3] === $this->_currentNickname) {
                    $this->getLogger()->log('Kicked from ' . $words[2]);
                    
                    $this->_removeChannel($words[2]);
                    
                    $this->_pluginBroker->kickedFromChannel($words[2]);
                } else {
                    $channel = $this->_getChannel($words[2]);
                    $channel->removeUser($words[3]);
                }
                break;
                
            case 'JOIN':
                $channel = $this->_getChannel($words[2]);
                $channel->addUser($words[3]);
                break;
                
            case 'PART':
                $channel = $this->_getChannel($words[2]);
                $channel->removeUser($words[3]);
                break;
        }
    }
    
    /**
     * Dispatch a command reply from the server
     *
     * @param  integer $responseCode The response code
     * @param  string  $line         The string from the server
     * @param  array   $words        The string splitted into words
     * @return void
     */
    protected function _dispatchCommandReply($responseCode, $line, array $words)
    {
        switch ($responseCode) {
            // Connected to server
            case '376':
                $this->_serverName = substr($words[0], 1);
                $this->getLogger()->log('Connected to server');

                $this->_delayTime = time();
                $this->_connected = true;

                $this->_pluginBroker->postConnect();
                break;
            
            // User list received
            case '353':
                $channel = $this->_getChannel($words[4], true);
                
                $users = array_slice($words, 5);
                foreach ($users as $user) {
                    if ($user[0] === '@' or $user[0] === '+') {
                        $user = substr($user, 1);
                    }
                    
                    $channel->addUser($user);
                }
                break;
        }
    }
    
    /**
     * Dispatch an error reply from the server
     *
     * @param  integer $responseCode The response code
     * @param  string  $line         The string from the server
     * @param  array   $words        The string splitted into words
     * @return void
     */
    protected function _dispatchErrorReply($responseCode, $line, array $words)
    {
        switch ($responseCode) {
            case '433':
                if ($this->_connected === true) {
                    $this->getLogger()->log('Nickname is in use, keeping alternative');
                } else {
                    $this->getLogger()->log('Nickname is in use, trying alternative');
                    
                    $alternative = $this->_nickname
                                 . '_'
                                 . substr(md5('ALL YOUR BASE ARE BELONG TO US'), rand(0, 31), 1);
                    
                    $response = DASBiT_Controller_Response::getInstance();
                    $response->sendRaw('NICK ' . $alternative);
                    
                    $this->_alternateNickname = $alternative;
                    $this->_currentNickname   = $this->_nickname;
                }
                break;
                
            case '474':
                $this->getLogger()->log('Cannot join channel ' . $words[2] . '(+b)');
                break;
        }
    }
    
    /**
     * Try to connect to the server
     *
     * @return void
     */
    protected function _connect()
    {
        $this->getLogger()->log('Connecting to server');
        
        $this->_pluginBroker->preConnect();
        
        while (true) {
            $this->_disconnect();
            $this->_socket = socket_create(AF_INET, SOCK_STREAM, 0);
            
            if ($this->_socket !== false) {
                $connectResult = socket_connect($this->_socket, $this->_address, $this->_port);
                
                if ($connectResult !== false) {
                    $nonBlockResult = socket_set_nonblock($this->_socket);    
                }
            }
            
            if ($this->_socket === false or $connectResult === false or $nonBlockResult === false) {
                $this->getLogger()->log('Could not connect, retrying in one minute');
                sleep(60);
                continue;
            } else {
                DASBiT_Controller_Response::getInstance()->setSocket($this->_socket);
                               
                $this->_sendNickname();
                $this->_sendUsername();
                
                $this->_currentNickname = $this->_nickname;
                $this->_lastPongTime    = time();
                break;
            }
        }
    }
    
    /**
     * Send the username to the server
     */
    protected function _sendUsername()
    {
        $this->getLogger()->log('Registering with server');
        
        $response = DASBiT_Controller_Response::getInstance();
        $response->sendRaw('USER ' . $this->_username . ' 2 3 :DASBiT');
    }
    
    /**
     * Send nickname to change to
     */
    protected function _sendNickname()
    {
        $this->getLogger()->log('Sending nickname');
        
        $response = DASBiT_Controller_Response::getInstance();
        $response->sendRaw('NICK ' . $this->_nickname);        
    }
    
    /**
     * Disconnect from the server, if connected
     */
    protected function _disconnect()
    {
        DASBiT_Controller_Response::getInstance()->setSocket(null);
        
        $this->_serverName = null;
        $this->_connected  = false;
        
        @socket_close($this->socket);
    }
    
    /**
     * Parse a raw channel name
     *
     * @param  string  $rawName Raw name of the channel
     * @param  boolean $reset   Wether to reset the channel or not
     * @return DASBiT_Irc_Channel
     */
    protected function _getChannel($rawName, $reset = false)
    {
        $channelName = addcslashes(trim($rawName), "'");
        if ($channelName[0] === ':') {
            $channelName = substr($channelName, 1);
        }
        
        if ($reset === true) {
            $this->_removeChannel($rawName);
        }
        
        if (isset($this->_channels[$channelName]) === false) {
            $this->_channels[$channelName] = new DASBiT_Irc_Channel($channelName);
        }
        
        return $this->_channels[$channelName];
    }
    
    /**
     * Remove a channel
     *
     * @param  string $rawName
     * @return void
     */
    protected function _removeChannel($rawName)
    {
        $channelName = addcslashes(trim($rawName), "'");
        if ($channelName[0] === ':') {
            $channelName = substr($channelName, 1);
        }
        
        if (isset($this->_channels[$channelName]) === true) {
            unset($this->_channels[$channelName]);
        }                
    }
    
    /**
     * Set the server to connect to
     *
     * @param  string $server
     * @throws InvalidArgumentException    When server is not a string
     * @throws DASBiT_Controller_Exception When server is not valid
     * @throws DASBiT_Controller_Exception When hostname is not valid
     * @return void
     */
    protected function _setServer($server)
    {
        if (is_string($server) === false) {
            throw new InvalidArgumentException('Server must be a string'); 
        }
        
        $split = explode(':', $server);
        
        if (count($split) === 2) {
            $host = $split[0];
            $this->_port = (int) $split[1];
        } else if (count($split) === 1) {
            $host = $server;
            $this->_port = 6667;
        } else {
            require_once 'DASBiT/Controller/Exception.php';
            throw new DASBiT_Controller_Exception('Server is not valid');
        }
        
        $address = gethostbyname($host);
        
        if (ip2long($address) === false or ($address === gethostbyaddr($address) and
           preg_match("#.*\.[a-zA-Z]{2,3}$#", $host) === 0) ) {
           require_once 'DASBiT/Controller/Exception.php';
           throw new DASBiT_Controller_Exception('Hostname is not valid');
        }
        
        $this->_address = $address;
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