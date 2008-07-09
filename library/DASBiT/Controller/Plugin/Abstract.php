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
 * Abstract class for controller plugins
 */
abstract class DASBiT_Controller_Plugin_Abstract
{
    /**
     * Called before dispatch loop starts
     *
     * @return void
     */
    public function init()
    {
    }
    
    /**
     * Called before the bot connects to the server
     *
     * @return void
     */
    public function preConnect()
    {
    }
    
    /**
     * Called every 5 seconds
     *
     * @return void
     */
    public function delayedCycle()
    {
    }
    
    /**
     * Called after the bot connected to the server
     *
     * @return void
     */
    public function postConnect()
    {
    }
    
    /**
     * Called before dispatching a priv msg
     *
     * @param  DASBiT_Controller_Request $request The request object
     * @return void
     */
    public function preDispatch(DASBiT_Controller_Request $request)
    {
    }
    
    /**
     * Called after dispatching a privmsg
     *
     * @param  DASBiT_Controller_Request $request The request object
     * @return void
     */
    public function postDispatch(DASBiT_Controller_Request $request)
    {
    }
    
    /**
     * Called after the bot was kicked from a channel
     *
     * @param  string $channel
     * @return void
     */
    public function kickedFromChannel($channel)
    {
    }
}