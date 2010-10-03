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
 * Client class for IRC servers
 */
class DASBiT_Irc_Client
{
    /**
     * Message types
     */
    const TYPE_MESSAGE = 'message';
    const TYPE_ACT     = 'act';
    const TYPE_NOTICE  = 'notice';
    
    /**
     * Controller which holds this client
     *
     * @var DASBiT_Irc_Controller
     */
    protected $_controller;
    
    /**
     * Address of the server
     *
     * @var string
     */
    protected $_address;
    
    /**
     * Port of the server
     *
     * @var integer
     */
    protected $_port;
    
    /**
     * Client's Nickname
     *
     * @var string
     */
    protected $_nickname;
    
    /**
     * Client's Username
     *
     * @var string
     */
    protected $_username;
    
    /**
     * Current active nickname
     *
     * @var string
     */
    protected $_currentNickname;
    
    /**
     * Last time a pong was received
     *
     * @var integer
     */
    protected $_lastPongTime;
    
    /**
     * Name of the server we are connected to
     *
     * @var string
     */
    protected $_serverName = null;
    
    /**
     * Delayed time
     *
     * @var integer
     */
    protected $_delayTime;
    
    /**
     * Connection status
     *
     * @var boolean
     */
    protected $_connected = false;
    
    /**
     * Socket to the IRC server
     *
     * @var resource
     */
    protected $_socket;
    
    /**
     * Create a new client connection to a server
     *
     * @param DASBiT_Irc_Controller $controller
     * @param string                $hostname
     * @param integer               $port
     */
    public function __construct(DASBiT_Irc_Controller $controller, $hostname, $port = 6777)
    {
        $address = gethostbyname($hostname);

        if (ip2long($address) === false || ($address === gethostbyaddr($address)
            && preg_match("#.*\.[a-zA-Z]{2,3}$#", $hostname) === 0) )
        {
           throw new DASBiT_Irc_Exception('Hostname is not valid');
        }

        $this->_controller = $controller;
        $this->_address    = $address;
        $this->_port       = $port;
        
        $this->_nickname = $controller->getConfig()->common->nickname;
        $this->_username = $controller->getConfig()->common->username;
    }
    
    /**
     * Get all new messages from the server
     * 
     * This method should be called as often as possible, so that no ping
     * timeout occurs.
     *
     * @return array
     */
    public function getRequests()
    {
        if (!$this->_connected) {
            $this->_connect();
        }
        
        $this->_delayedChecks();
        
        $requests = array();
        
        $read   = array($this->_socket);
        $write  = array();
        $except = null;
        $select = socket_select($read, $write, $except, 1);
        $buffer = '';
        
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
                        $request = $this->_dispatchLine(trim($line));
                        
                        if ($request !== null) {
                            $requests[] = $request;
                        }
                    }

                    $buffer    = substr($buffer, $lineBreak + 1);
                    $lineBreak = strpos($buffer, "\n");
                }
            } else {
                $errorString = socket_strerror(socket_last_error($this->_socket));
                $this->_controller->log('Connection to server lost, sleeping for one minute. Reason: ' . $errorString);
                $this->_disconnect();
                sleep(60);
            }
        }
        
        return $requests;
    }
    
    /**
     * Send a raw message to the server
     *
     * @param  string $string
     * @return void
     */
    public function sendRaw($string)
    {
        if (!$this->_connected) {
            $this->_connect();
        }
        
        $result = @socket_write($this->_socket, $string . "\n", strlen($string) + 1);

        if (!$result) {
            $this->_disconnect();
        }
    }
    
    /**
     * Send a message to a user or channel
     *
     * @param  string                           $message The message to send
     * @param  string|DASBiT_Controller_Request $target  Where to send the message
     * @param  string                           $type    Type of the message, see
     *                                                   DASBiT_Controller_Response::TYPE_*
     * @return DASBiT_Controller_Response    
     */
    public function send($message, $target, $type = self::TYPE_MESSAGE)
    {
        if ($target instanceof DASBiT_Irc_Request) {
            if ($type === self::TYPE_NOTICE) {
                $target = $target->getNickname();
            } else {
                $target = $target->getSource();
            }
        }
        
        switch ($type) {
            case self::TYPE_MESSAGE:
                $this->sendRaw('PRIVMSG ' . $target . ' :' . $message);
                break;
                
            case self::TYPE_ACT:
                $chr = chr(1);
                $this->sendRaw('PRIVMSG ' . $target . ' :' . $chr . 'ACTION ' . $message . $chr);
                break;
                
            case self::TYPE_NOTICE:
                $this->sendRaw('NOTICE ' . $target . ' :' . $message);
                break;
        }
        
        return $this;
    }
    
    
    /**
     * Dispatch a line from the server
     *
     * @param  string $line
     * @return DASBiT_Irc_Request|null
     */
    protected function _dispatchLine($line)
    {
        $words = explode(' ', $line);
		//$this->_controller->log($line);
        
        // Respond to ping
        if ($words[0] === 'PING' and isset($words[1]) === true) {
            $this->sendRaw('PONG ' . $words[1]);
            return null;
        }
        
        // If there are no further arguments, ignore it
        if (isset($words[1]) === false) {
            return null;
        }
        
        // See if there is a response code
        if (is_numeric($words[1])) {
            $responseCode = (int) $words[1];
            
            if ($responseCode > 400) {
                $this->_dispatchErrorReply($responseCode, $line, $words);
            } else {
                $this->_dispatchCommandReply($responseCode, $line, $words);
            }
            
            if ($responseCode === 353) {
                $this->_controller->triggerHook('channellist', $words);
            }
            
        } else if ($words[1] === 'PRIVMSG') {
            return $this->_parsePrivMsg($line, $words);

        } else if ($words[1] === 'NOTICE') {
            $this->_controller->triggerHook('notice', $words);

        } else {
            // Else handle the command
            $this->_dispatchCommand($words[1], $line, $words);
        }
        
        return null;
    }    
   
    /**
     * Parse a private message
     *
     * @param  string $line  The string frm the server
     * @param  array  $words The string splitted into words
     * @return DASBiT_Irc_Request|null]
     */
    protected function _parsePrivMsg($line, array $words)
    {
        // See what this is
        if (count($words) >= 4 and $words[2] === $this->_currentNickname &&
                strpos($words[3], chr(1) !== false)) {
            // Looks like a CTCP command
            if (preg_match('#^.*' . chr(1) . '([^ ]+)(.*)' . chr(1) . '.*$#', $line, $match) === 1) {
                $ctcpCommand = strtoupper($match[1]);
                $request = new DASBiT_Irc_Request($line, $this->_currentNickname);
                switch($ctcpCommand){
                    CASE 'VERSION':
                        $this->send(chr(1) . $ctcpCommand . ' DASBiT PHP/Zend Framework IRC Bot v' .
                                DASBiT_Version::getVersion() . ' on ' . PHP_OS . chr(1),
                                $request, DASBiT_Irc_Client::TYPE_NOTICE);
                        break;
                    CASE 'PING':
                        $this->send(chr(1) . $ctcpCommand . $match[2] . chr(1),
                                $request, DASBiT_Irc_Client::TYPE_NOTICE);
                        break;
                    CASE 'FINGER':
                        break;
                    CASE 'CLIENTINFO':
                        break;
		    CASE 'TIME':
			$this->send(chr(1) . $ctcpCommand . ' ' . date('l jS \of F Y h:i:s A') . chr(1),
                                $request, DASBiT_Irc_Client::TYPE_NOTICE);
			break;
                    CASE 'SOURCE':
                        $this->send(chr(1) . $ctcpCommand . ' http://dasbit.svn.dasprids.de/' . chr(1),
                                $request, DASBiT_Irc_Client::TYPE_NOTICE);
                        break;
                    default:
                        $this->send(chr(1) . 'ERRMSG ' . $ctcpCommand . ' : Unknown query' . chr(1),
                                $request, DASBiT_Irc_Client::TYPE_NOTICE);
                        break;
                }
            }
            
            return null;
        } else {
            // This is a default message
            $request = new DASBiT_Irc_Request($line, $this->_currentNickname);
            
            return $request;
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
                if ($this->_nickname !== $this->_currentNickname && 
                    preg_match('#:' . $this->_currentNickname
                               . '[^ ]+ NICK :'
                               . $this->_nickname
                               . '#',
                               $line) === 1) {
                    $this->_controller->log('Original nickname regained');
                    
                    $this->_currentNickname = $this->_nickname;
                }
				preg_match('#^:([^!]+)!#', $words[0], $matches);
				$newnick = ltrim($words[2], ":");
				$this->_controller->triggerHook('changednick', array('oldnickname' => $matches[1], 'newnickname' => $newnick));
                break;
                
            case 'PONG':
                $this->_lastPongTime = time();
                break;

            case 'KICK':
                if ($words[3] === $this->_currentNickname) {
                    // Self kicked from channel
                    $this->_controller->log('Kicked from ' . $words[2]);
                    $this->_controller->triggerHook('kicked');
                } else {
					$this->_controller->triggerHook('userkicked', array('nickname' => $words[3], 'channel' => $words[2]));
                }
                break;
                
            case 'JOIN':
				preg_match('#^:([^!]+)!#', $words[0], $matches);
				$joinchannel = ltrim($words[2], ":");
				$this->_controller->triggerHook('userjoin', array('nickname' => $matches[1], 'channel' => $joinchannel));
			    break;
                
            case 'PART':
				preg_match('#^:([^!]+)!#', $words[0], $matches);
				$this->_controller->triggerHook('userpart', array('nickname' => $matches[1], 'channel' => $words[2]));
                break;
			case 'QUIT':
				preg_match('#^:([^!]+)!#', $words[0], $matches);
				$this->_controller->triggerHook('userquit', array('nickname' => $matches[1]));
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
                $this->_controller->log('Connected to server');

                $this->_delayTime = time();
                
                $this->_controller->triggerHook('connected');
                break;
            
            // User list received
            case '353':
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
                if ($this->_nickname !== $this->_currentNickname) {
                    $this->_controller->log('Nickname is in use, keeping alternative');
                } else {
                    $this->_controller->log('Nickname is in use, trying alternative');
                    
                    $alternative = $this->_nickname
                                 . '_'
                                 . substr(md5('ALL YOUR BASE ARE BELONG TO US'), rand(0, 31), 1);
                    
                    $this->sendRaw('NICK ' . $alternative);
                    
                    $this->_currentNickname = $alternative;
                }
                break;
                
            case '474':
                $this->_controller->log('Cannot join channel ' . $words[2] . '(+b)');
                break;
        }
    }
    
    /**
     * Run delayed checks, like regain of original nick or check of lag time
     *
     * @return void 
     */
    protected function _delayedChecks()
    {
        if ($this->_connected && $this->_serverName !== null) {
            if ($this->_delayTime + 5 > time()) {
                return;
            }

            if ($this->_lastPongTime + 60 <= time()) {
                $this->_controller->log('Maximum lag reached, reconnecting');
                
                $this->_connect();
            }
            
            if ($this->_nickname !== $this->_currentNickname) {
                $this->_controller->log('Trying to regain original nick');
                
                $this->_sendNickname();
            }
            
            $this->sendRaw('PING ' . $this->_serverName);
            
            $this->_delayTime = time();
        }
    }
    
    /**
     * Try to connect to the server
     *
     * @return void
     */
    protected function _connect()
    {
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
                $this->_controller->log('Could not connect, retrying in one minute');
                sleep(60);
                continue;
            }
            
            $this->_connected       = true;
            $this->_currentNickname = $this->_nickname;
            $this->_lastPongTime    = time();
                        
            $this->_sendNickname();
            $this->_sendUsername();

            break;
        }
    }
    
    /**
     * Send the username to the server
     * 
     * @return void
     */
    protected function _sendUsername()
    {
        $this->sendRaw('USER ' . $this->_username . ' 2 * :DASBiT');
    }
    
    /**
     * Send nickname to change to
     * 
     * @return void
     */
    protected function _sendNickname()
    {
        $this->sendRaw('NICK ' . $this->_nickname);        
    }
    
    /**
     * Disconnect from the server, if connected
     * 
     * @return void
     */
    protected function _disconnect()
    {
        $this->_serverName = null;
        $this->_connected  = false;
        
        @socket_close($this->socket);
    }
}
