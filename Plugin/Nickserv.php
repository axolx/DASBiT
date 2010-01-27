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
 * @version $Id: Channels.php 45 2009-08-21 09:59:57Z necrogami $
 */

/**
 * Plugin to handle Nickserv authentication
 */
class Plugin_Nickserv extends DASBiT_Plugin
{
    /**
     * Defined by DASBiT_Plugin
     *
     * @return void
     */
    protected function _init()
    {
        $this->_controller->registerHook($this, 'connected', 'connected', 100);
    }
    
    /**
     * Hook called after connect
     *
     * @return void
     */
    public function connected()
    {
        $config = $this->_controller->getConfig()->nickserv;

        if (!empty($config->username) && !empty($config->password)) {
            $this->_client->send('identify ' . $config->username . ' ' . $config->password, 'nickserv');
        }
    }
}
