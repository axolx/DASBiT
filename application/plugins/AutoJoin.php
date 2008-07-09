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
 * @see DASBiT_Controller_Plugin_Abstract
 */
require_once 'DASBiT/Controller/Plugin/Abstract.php';

/**
 * Plugin for automatically joining previous channels after connect
 */
class Plugin_AutoJoin extends DASBiT_Controller_Plugin_Abstract
{
    /**
     * Autjoin earlier channels
     *
     * @return void
     */
    public function postConnect()
    {
        $response = DASBiT_Controller_Response::getInstance();

        $channels = simplexml_load_file(dirname(__FILE__) . '/../data/channels.xml');
        foreach ($channels as $channel) {
            $channel = (string) $channel;
            
            $response->sendRaw('JOIN ' . $channel);
            DASBiT_Controller_Front::getInstance()->getLogger()->log('Joined ' . $channel);
        }
    }
}