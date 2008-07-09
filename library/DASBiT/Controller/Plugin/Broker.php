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
        $this->_plugins[] = $plugin;
    }
    
    /**
     * Called before dispatch loop starts
     *
     * @return void
     */
    public function init()
    {
        foreach ($this->_plugins as $plugin) {
            $plugin->init();
        }
    }
    
    /**
     * Called every 5 seconds
     * 
     * @return void
     */
    public function delayedCycle()
    {
        foreach ($this->_plugins as $plugin) {
            $plugin->delayedCycle();
        }
    }
    
    /**
     * Called before the bot connects to the server
     *
     * @return void
     */
    public function preConnect()
    {
        foreach ($this->_plugins as $plugin) {
            $plugin->preConnect();
        }
    }
    
    /**
     * Called after the bot connected to the server
     *
     * @return void
     */
    public function postConnect()
    {
        foreach ($this->_plugins as $plugin) {
            $plugin->postConnect();
        }
    }
    
    /**
     * Called before dispatching a priv msg
     *
     * @param  DASBiT_Controller_Request $request The request object
     * @return void
     */
    public function preDispatch(DASBiT_Controller_Request $request)
    {
        foreach ($this->_plugins as $plugin) {
            $plugin->preDispatch($request);
        }
    }
    
    /**
     * Called after dispatching a privmsg
     *
     * @param  DASBiT_Controller_Request $request The request object
     * @return void
     */
    public function postDispatch(DASBiT_Controller_Request $request)
    {
        foreach ($this->_plugins as $plugin) {
            $plugin->postDispatch($request);
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
