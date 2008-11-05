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
     * Create a new controller instance
     *
     * @param Zend_Config $config
     */
    public function __construct(Zend_Config $config)
    {
        // Set configuration
        $this->_config = $config;
        
        // Create server instance
        $this->_client = new DASBiT_Irc_Client($this,
                                               $this->_config->server->hostname,
                                               $this->_config->server->port);
    }
}