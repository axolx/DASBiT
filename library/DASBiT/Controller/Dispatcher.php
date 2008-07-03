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
     * Socket
     *
     * @var resource
     */
    protected $_socket;
    
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
            $select = socket_select(array($r), null, null, 1);
            if ($select !== false) {
                $buffer = socket_read($this->_socket, 10240);
                
                if ($buffer !== false) {
                    
                    $lineBreak = strpos($buffer, "\n");
                    while ($lineBreak !== false) {
                        $line = substr($buffer, 0, $lineBreak);

                        if (strlen($line) > 0) {
                            $this->_dispatch($line);
                        }

                        $buffer    = substr($buffer, $lineBreak + 1);
                        $lineBreak = strpos($buffer, "\n");
                    }
                } else {
                    DASBiT_Controller_Front::getInstance()->getLogger()->log('Connecting to server lost, reconnecting in one minute');
                    sleep(60);

                    $this->_connect();
                    $this->_register();

                    $this->_printlog('connection successful, resuming main loop', 1);
                }
            }
        }
    }
    
    /**
     * Dispatch a line from the server
     *
     * @param string $line
     */
    protected function _dispatch($line)
    {
        
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
        $response->sendRaw('USER ' . DASBiT_Controller_Front::getInstance()->getUsername() . ' 2 3 :DASBiT')
                 ->sendRaw('NICK ' . DASBiT_Controller_Front::getInstance()->getNickname());
    }
    
    /**
     * Disconnect from the server, if connected
     */
    protected function _disconnect()
    {
        DASBiT_Controller_Response::setSocket(null);
        @socket_close($this->socket);
    }
}