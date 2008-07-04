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
 * Controller Plugin broker
 */
class DASBiT_Controller_Plugin_Broker
{
    /**
     * Plugins registered with the broker
     *
     * @var array
     */
    protected $_plugins = array();
    
    /**
     * Register a plugin with the broker
     *
     * @param  DASBiT_Controller_Plugin_Abstract $plugin
     * @return void
     */
    public function registerPlugin(DASBiT_Controller_Plugin_Abstract $plugin)
    {
        $this->_plugins = $plugin;
    }
    
    /**
     * Called after the bot connected to the server
     *
     * @return void
     */
    public function connectedToServer()
    {
        foreach ($this->_plugins as $plugin) {
            $plugin->connectedToServer();
        }
    }
    
    /**
     * Called after the bot was kicked from a channel
     *
     * @param  string $channel
     * @return void
     */
    public function kickedFromChannel($channel)
    {
        foreach ($this->_plugins as $plugin) {
            $plugin->kickedFromChannel($channel);
        }
    }
}