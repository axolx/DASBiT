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
 * @see JiraModel
 */
require_once 'JiraModel.php';

/**
 * Controller for controlling jira watching
 */
class JiraController implements DASBiT_Controller_Action_Interface
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
        if (count($words) < 4) {
            $response->send('Not enough parameters', $request);
            return;
        }

        list(, $mode, $channel, $jiraUrl) = $words;
        
        $jiraUrl = rtrim($jiraUrl, '/');
        
        $jiraModel = new JiraModel();
        $db        = $jiraModel->getAdapter();
        
        switch ($mode) {           
            case 'add-watch':
                $url = $jiraUrl
                     . '/sr/jira.issueviews:searchrequest-xml/temp/SearchRequest.xml'
                     . '?&pid=10000'
                     . '&sorter/field=issuekey'
                     . '&sorter/order=DESC'
                     . '&sorter/field=updated'
                     . '&sorter/order=DESC'
                     . '&tempMax=1';
                     
                $issues = simplexml_load_file($url);
                if ($issues === false) {
                    $response->send('Invalid Jira URL', $request);
                }
                
                $key = (string) $issues->channel->item->key;
                
                $jiraModel->insert(array('jira_channel'    => $channel,
                                         'jira_url'        => $jiraUrl,
                                         'jira_last_issue' => $key));
                break;
                
            case 'remove-watch':
                $jiraModel->delete(array($db->quoteInto('jira_channel = ?', $channel),
                                         $db->quoteInto('jira_url = ?', $jiraUrl)));
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
        return '<add-watch|remove-watch> <url>';
    }
}