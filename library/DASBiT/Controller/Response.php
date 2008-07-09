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
 * Response object which sends to the server.
 * 
 * This is a singleton
 */
class DASBiT_Controller_Response
{
    /**
     * Send types
     */
    const TYPE_MESSAGE = 'message';
    const TYPE_ACT     = 'act';
    const TYPE_NOTICE  = 'notice';
    
    /**
     * Instance of the singleton
     *
     * @var DASBiT_Controller_Response
     */
    protected static $_instance = null;
    
    /**
     * Socket
     *
     * @var resource
     */
    protected $_socket = null;
    
    /**
     * When was the last message sent
     *
     * @var int
     */
    protected $_lastMessageTime = null;
       
    /**
     * Singleton constructor
     */
    protected function __construct()
    {
    }
    
    /**
     * Get the singleton instance
     *
     * @return DASBiT_Controller_Response
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        
        return self::$_instance;
    }
    
    /**
     * Set the socket to respond to
     *
     * @param resource $socket
     */
    public function setSocket($socket)
    {
        $this->_socket = $socket;
    }
    
    /**
     * Send a raw message to the socket
     *
     * @param  string $string
     * @throws DASBiT_Controller_Exception When socket was not set
     * @return DASBiT_Controller_Response
     */
    public function sendRaw($string)
    {
        if ($this->_socket === null) {
            require_once 'DASBiT/Controller/Exception.php';
            throw new DASBiT_Controller_Exception('Not connected to server');
        }
        
        socket_write($this->_socket, $string . "\n", strlen($string) + 1);
        
        return $this;
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
        if ($this->_lastMessageTime !== null) {
            while (($this->_lastMessageTime + 1) > time()) {
                sleep(1);
            }            
        }
        
        if ($target instanceof DASBiT_Controller_Request) {
            $target = $target->getSource();
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
        
        $this->_lastMessageTime = time();
        
        return $this;
    }
    
    /**
     * Disallow cloning of response object
     * 
     * @throws DASBiT_Controller_Exception As cloning is not allowed
     * @return void
     */
    public function __clone()
    {
        require_once 'DASBiT/Controller/Exception.php';
        throw new DASBiT_Controller_Exception('Cloning of response object is not allowed');
    }
}