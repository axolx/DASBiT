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
 * @see DASBiT_Controller_Response
 */
require_once 'DASBiT/Controller/Response.php';

/**
 * @see DASBiT_Controller_Request
 */
require_once 'DASBiT/Controller/Request.php';

/**
 * Dispatcher which handles all IRC related stuff
 */
class DASBiT_Controller_Dispatcher
{
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
     * Socket
     *
     * @var resource
     */
    protected $_socket;
    
    /**
     * Time for delayed checks 
     *
     * @var integer
     */
    protected $_delayTime; 
    
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
     * Set the server to connect to
     *
     * @param  string $server
     * @throws InvalidArgumentException    When server is not a string
     * @throws DASBiT_Controller_Exception When server is not valid
     * @throws DASBiT_Controller_Exception When hostname is not valid
     * @return DASBiT_Controller_Dispatcher
     */
    public function setServer($server)
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
        
        if (ip2long($address) === false or ($ip === gethostbyaddr($ip) and
           preg_match("#.*\.[a-zA-Z]{2,3}$#", $host) === 0) ) {
           require_once 'DASBiT/Controller/Exception.php';
           throw new DASBiT_Controller_Exception('Hostname is not valid');
        }
        
        $this->_address = $address;
        
        return $this;
    }
    
    /**
     * Main dispatch loop
     * 
     * This one while run *forever*, so don't plan to do stuff after calling this
     *
     * @return void
     */
    public function dispatchLoop()
    {
        $this->_connect();
        
        while (true) {
            $this->_delayedChecks();
            
            $select = socket_select(array($r), null, null, 1);
            if ($select !== false) {
                $buffer = socket_read($this->_socket, 10240);
                
                if ($buffer !== false) {
                    
                    $lineBreak = strpos($buffer, "\n");
                    while ($lineBreak !== false) {
                        $line = substr($buffer, 0, $lineBreak);

                        if (strlen($line) > 0) {
                            $this->_dispatch(trim($line));
                        }

                        $buffer    = substr($buffer, $lineBreak + 1);
                        $lineBreak = strpos($buffer, "\n");
                    }
                } else {
                    DASBiT_Controller_Front::getInstance()->getLogger()->log('Connecting to server lost, reconnecting in one minute');
                    sleep(60);

                    $this->_connect();

                    $this->_printlog('connection successful, resuming main loop', 1);
                }
            }
        }
    }
    
    /**
     * Run delayed checks, like regain of original nick or lag time
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
                DASBiT_Controller_Front::getInstance()->getLogger()->log('Maximum lag reached, reconnecting');
                
                $this->_connect();
            }
            
            if ($this->_nicknameInUse === true) {
                DASBiT_Controller_Front::getInstance()->getLogger()->log('Trying to regain original nick');
                
                $this->_sendNickname();
            }
            
            $reponse = new DASBiT_Controller_Response();
            $response->sendRaw('PING ' . $this->_serverName);
        }
    }
    
    /**
     * Dispatch a line from the server
     *
     * @param string $line
     */
    protected function _dispatch($line)
    {
        $words    = explode(' ', $line);
        $response = new DASBiT_Controller_Response();
        
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
            $responseCode = int($words[1]);
            
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
            // @todo Call controllers with the request
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
                               . DASBiT_Controller_Front::getInstance()->getNickname()
                               . '#',
                               $line) === 1) {
                    DASBiT_Controller_Front::getInstance()->getLogger()->log('Original nickname regained');
                    
                    $this->_nicknameInUse     = false;
                    $this->_alternateNickname = null;
                    $this->_currentNickname   = DASBiT_Controller_Front::getInstance()->getNickname();
                }
                break;
                
            case 'PONG':
                $this->_lastPongTime = time();
                break;

            case 'KICK':
                if ($words[3] === $this->_currentNickname) {
                    DASBiT_Controller_Front::getInstance()->getLogger()->log('Kicked from ' . $words[2]);
                    
                    // @todo Kicked plugin here
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
            case '376':
                $this->_serverName = substr($words[0], 1);
                DASBiT_Controller_Front::getInstance()->getLogger()->log('Connected to server');

                $this->_delayTime = time();
                $this->_connected = true;

                // @todo Connect plugin here
                break;
                
            case '353':
                $channel = $this->_getChannel($words[4]);
                
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
                    DASBiT_Controller_Front::getInstance()->getLogger()->log('Nickname is in use, keeping alternative');
                } else {
                    DASBiT_Controller_Front::getInstance()->getLogger()->log('Nickname is in use, trying alternative');
                    
                    $alternative = DASBiT_Controller_Front::getInstance()->getNickname()
                                 . '_'
                                 . substr(md5('ALL YOUR BASE ARE BELONG TO US'), rand(0, 31), 1);
                    
                    $reponse->sendRaw('NICK ' . $alternative);
                    
                    $this->_alternateNickname = $alternative;
                    $this->_currentNickname   = DASBiT_Controller_Front::getInstance()->getNickname();
                }
                break;
                
            case '474':
                DASBiT_Controller_Front::getInstance()->getLogger()->log('Cannot join channel ' . $words[2] . '(+b)');
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
        DASBiT_Controller_Front::getInstance()->getLogger()->log('Connecting to server');
        
        while (true) {
            $this->_disconnect();
            $this->_socket = socket_create(AF_INET, SOCK_STREAM, 0);

            if ($this->_socket < 0 or
                socket_connect($this->_socket, $this->_address, $this->_port) === false or
                socket_set_nonblock($this->_socket) === false) { 
                DASBiT_Controller_Front::getInstance()->getLogger()->log('Could not connect, retrying in one minute');
                sleep(60);
                continue;
            } else {
                DASBiT_Controller_Response::setSocket($this->_socket);
                               
                $this->_sendNickname();
                $this->_register();
                
                $this->_currentNickname = DASBiT_Controller_Front::getInstance()->getNickname();
                $this->_lastPongTime    = time();
                break;
            }
        }
    }
    
    /**
     * Register with the server
     */
    protected function _register()
    {
        DASBiT_Controller_Front::getInstance()->getLogger()->log('Registering with server');
        
        $response = new DASBiT_Controller_Response();
        $response->sendRaw('NICK ' . DASBiT_Controller_Front::getInstance()->getNickname());
    }
    
    /**
     * Send nickname to change to
     */
    protected function _sendNickname()
    {
        DASBiT_Controller_Front::getInstance()->getLogger()->log('Sending nickname');
        
        $response = new DASBiT_Controller_Response();
        $response->sendRaw('USER ' . DASBiT_Controller_Front::getInstance()->getUsername() . ' 2 3 :DASBiT');        
    }
    
    /**
     * Disconnect from the server, if connected
     */
    protected function _disconnect()
    {
        DASBiT_Controller_Response::setSocket(null);
        
        $this->_serverName = null;
        $this->_connected  = false;
        
        @socket_close($this->socket);
    }
    
    /**
     * Parse a raw channel name
     *
     * @param  string $rawName
     * @return DASBiT_Irc_Channel
     */
    protected function _getChannel($rawName)
    {
        $channelName = addcslashes(trim($rawName), "'");
        if ($channelName[0] === ':') {
            $channelName = substr($channelName, 1);
        }
        
        if (isset($this->_channels[$channelName]) === false) {
            $this->_channels[$channelName] = new DASBiT_Controller_Channel($channelName);
        }
        
        return $this->_channels[$channelName];
    }
}