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
 * Abstract plugin class
 */
abstract class DASBiT_Plugin
{
    /**
     * Parent controller
     *
     * @var DASBiT_Irc_Controller
     */
    protected $_controller;
    
    /**
     * Client used to send messages
     *
     * @var DASBiT_Irc_Client
     */
    protected $_client;
    
    /**
     * Assign the controller and it's client locally
     *
     * @param DASBiT_Irc_Controller $controller
     */
    public function __construct(DASBiT_Irc_Controller $controller)
    {
        $this->_controller = $controller;
        $this->_client     = $controller->getClient();
        
        $this->_init();
    }
    
    /**
     * Initiate the plugin
     *
     * @return void
     */
    abstract protected function _init();
}