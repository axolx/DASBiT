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
 * @see DASBiT_Controller_Action_Interface
 */
require_once 'DASBiT/Controller/Action/Interface.php';

/**
 * Controller for joining channels
 */
class Part_Controller implements DASBiT_Controller_Action_Interface
{
    /**
     * Defined by DASBiT_Controller_Action_Interface
     *
     * @param  DASBiT_Controller_Request  $request
     * @param  DASBIT_Controller_Response $response
     * @return void
     */
    public function dispatch(DASBiT_Controller_Request $request, DASBIT_Controller_Response $response)
    {
        if (UsersPlugin::isIdentified($request) === false) {
            $response->send('You are not identified', $request);
            return;            
        }
        
        $words = explode(' ', $request->getMessage());
        if (count($words) < 2) {
            $response->send('Not enough parameters', $request);
            return;
        }

        list(, $channel) = $words;
        
        $response->sendRaw('PART ' . $channel);
        
        $channelsModel = new ChannelsModel();
        $channelsModel->delete($channelsModel->getAdapter()
                                             ->quoteInto('channel_name = ?',
                                                         $channel));
                                                         
        DASBiT_Controller_Front::getInstance()->getLogger()->log('Parted ' . $channel);
    }

    /**
     * Defined by DASBiT_Controller_Action_Interface
     *
     * @return string
     */
    public function getHelp()
    {
        return '<channel>';
    }
}