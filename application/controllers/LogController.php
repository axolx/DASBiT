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
 * @see ChannelsModel
 */
require_once 'ChannelsModel.php';

/**
 * Controller for controlling log watching
 */
class LogController implements DASBiT_Controller_Action_Interface
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

        list(, $mode, $channel) = $words;
        
        $channelsModel = new ChannelsModel();
        $db            = $channelsModel->getAdapter();
        switch ($mode) {
            case 'add-watch':
                $channelsModel->update(array('channel_log' => 1),
                                       $db->quoteInto('channel_name = ?', $channel));
                break;
                
            case 'remove-watch':
                $channelsModel->update(array('channel_log' => 0),
                                       $db->quoteInto('channel_name = ?', $channel));
                break;
                
            default:
                $response->send('Unknown mode', $request);
                break;
        }
    }

    /**
     * Defined by DASBiT_Controller_Action_Interface
     *
     * @return string
     */
    public function getHelp()
    {
        return '<add-watch|remove-watch> <channel>';
    }
}