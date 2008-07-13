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
 * @see JiraModel
 */
require_once 'JiraModel.php';

/**
 * Plugin for watching jira issues
 */
class JiraPlugin extends DASBiT_Controller_Plugin_Abstract
{   
    /**
     * Delay time for jira checks
     *
     * @var integer
     */
    protected $_delayTime = 0;
           
    /**
     * Check every 2 mintues for jira changes
     *
     * @return void
     */
    public function delayedCycle()
    {
        if ($this->_delayTime + 60 * 2 <= time()) {
            $response  = DASBiT_Controller_Response::getInstance();
            $jiraModel = new JiraModel();
            $jiras     = $jiraModel->fetchAll();
            
            foreach ($jiras as $jira) {
                $lastIssue = (string) $jira->jira_last_issue;
                
                $url = $jira->jira_url
                     . '/sr/jira.issueviews:searchrequest-xml/temp/SearchRequest.xml'
                     . '?&pid=10000'
                     . '&updated%3Aprevious=-1d'
                     . '&sorter/field=issuekey'
                     . '&sorter/order=DESC'
                     . '&sorter/field=updated'
                     . '&sorter/order=DESC'
                     . '&tempMax=1000';
                               
                $issues = simplexml_load_file($url);
                
                if ($issues === false) {
                    continue;
                }

                $currentLastIssue = null;
                foreach ($issues->item as $issue) {
                    $key = (string) $key;

                    if ($currentLastIssue === null) {
                        $currentLastIssue = $key;
                    }
                    
                    if ($key === $lastIssue) {
                        break;
                    }
                    
                    $type      = (string) $issue->type;
                    $summary   = (string) $issue->summary;
                    $status    = (string) $issue->status;
                    $component = (string) $issue->component;
                    
                    $tinyUrl = file_get_contents('http://tinyurl.com/api-create.php?url='
                                                             . $jira->jira_url . '/browse/ ' . $key);
                    
                    $message = '[Jira:' . $key . '] '
                             . '[Type:' . $type . ']'
                             . '[Status:' . $status . '] '
                             . '[Component:' . $component . '] '
                             . $summary
                             . ' (See: ' . $tinyUrl .')';
                             
                    $response->send($message, $jira->jira_channel);
                }
                
                $jira->jira_last_issue = $currentLastIssue;
                $jira->save();
            }

            $this->_delayTime = time();
        }
    }
}