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
 * Plugin to handle JIRA issues
 * 
 * Currently this plugin is specific for ZF Jira
 */
class Plugin_Jira extends DASBiT_Plugin
{
    /**
     * Database adapter
     *
     * @var Zend_Db_Adapter_Pdo_Sqlite
     */
    protected $_adapter;
    
    /**
     * Defined by DASBiT_Plugin
     *
     * @return void
     */
    protected function _init()
    {
        $this->_adapter = DASBiT_Database::accessDatabase('jira', array(
            'trackers' => array(
                'tracker_id'         => 'INTEGER PRIMARY KEY',
                'tracker_last_issue' => 'VARCHAR(16)'
            )
        ));
        
        $select = $this->_adapter
                       ->select()
                       ->from('trackers',
                              array('tracker_id',
                                    'tracker_last_issue'));
                              
        $tracker = $this->_adapter->fetchRow($select);
        
        if ($tracker === false) {
            $this->_adapter->insert('trackers',
                                    array('tracker_id'         => 1,
                                          'tracker_last_issue' => ''));
        }
        
        $this->_controller->registerCommand($this, 'lookupIssue', 'issue');
        $this->_controller->registerInterval($this, 'watchUpdates', 120);
        $this->_controller->registerTrigger($this, 'lookupIssues', '#@ZF-\d+#i');
    }
    
    /**
     * Lookup triggered issues
     * 
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function lookupIssues(DASBiT_Irc_Request $request)
    {
        preg_match_all('#@ZF-(\d+)#i', $request->getMessage(), $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $issueId = 'ZF-' . $match[1];
            
            $uri = sprintf('http://framework.zend.com/issues/si/jira.issueviews:issue-xml/%1$s/%1$s.xml', urlencode($issueId));
            
            $client   = new Zend_Http_Client($uri);
            $response = $client->request();
    
            if (!$response->isSuccessful()) {
                $this->_client->send('No issue with this ID found', $request);
                continue;
            }
            
            $xml = @simplexml_load_string($response->getBody());
            
            if (!$xml) {
                $this->_client->send('Unable to reach JIRA', $request);
                continue;    
            }
            
            $item = $xml->channel->item;
                    
            $this->_reportIssue('Issue', $item, $request);
        }
    }
    
    /**
     * Lookup a Jira issue
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function lookupIssue(DASBiT_Irc_Request $request)
    {
        $words = $request->getWords();
        
        if (!isset($words[1])) {
            $this->_client->send('An issue ID must be supplied', $request);
            return;    
        }
        
        $issueId = $words[1];
        
        if (is_numeric($issueId)) {
            $issueId = 'ZF-' . $issueId;
        }
        
        $uri = sprintf('http://framework.zend.com/issues/si/jira.issueviews:issue-xml/%1$s/%1$s.xml', urlencode($issueId));
        
        $client   = new Zend_Http_Client($uri);
        $response = $client->request();

        if (!$response->isSuccessful()) {
            $this->_client->send('No issue with this ID found', $request);
            return;
        }
        
        $xml = @simplexml_load_string($response->getBody());
        
        if (!$xml) {
            $this->_client->send('Unable to reach JIRA', $request);
            return;    
        }
        
        $item = $xml->channel->item;
                
        $this->_reportIssue('Issue', $item, $request);
    }

    /**
     * Watch for updates
     *
     * @return void
     */
    public function watchUpdates()
    {
        $select = $this->_adapter
                       ->select()
                       ->from('trackers',
                              array('tracker_id',
                                    'tracker_last_issue'));
                              
        $tracker = $this->_adapter->fetchRow($select);
        
        if ($tracker['tracker_last_issue'] === '') {
            $issues = $this->_getLatestIssues(1);

            if (isset($issues->channel->item->key)) {
                $lastIssue = (string) $issues->channel->item->key;
            } else {
                $lastIssue = '';
            }
            
            $this->_adapter->update('trackers',
                                    array('tracker_last_issue' => $lastIssue),
                                    'tracker_id = 1');
            return;
        }
        
        $issues = $this->_getLatestIssues(100);
        
        if (!$issues) {
            return;
        }

        $lastIssue        = $tracker['tracker_last_issue'];
        $currentLastIssue = null;

        if (is_array($issues->channel->item) === true) {
            $issues = $issues->channel->item;
        } else {
            $issues = array($issues->channel->item);
        }
        
        foreach ($issues as $issue) {
            $key = (string) $issue->key;
            
            if ($currentLastIssue === null) {
                $currentLastIssue = $key;
            }
            
            if ($key === $lastIssue) {
                break;
            }
            
            $this->_reportIssue('Issue-Update', $issue, null);
        }
        
        $this->_adapter->update('trackers',
                                array('tracker_last_issue' => $currentLastIssue),
                                'tracker_id = 1');
    }
    
    /**
     * Get latest issues
     * 
     * @param  integer $num
     * @return SimpleXMLElement
     */
    protected function _getLatestIssues($num)
    {
        $uri = 'http://framework.zend.com/issues/sr/jira.issueviews:searchrequest-xml/temp/SearchRequest.xml'
             . '/sr/jira.issueviews:searchrequest-xml/temp/SearchRequest.xml'
             . '?&pid=10000'
             . '&updated%3Aprevious=-1d'
             . '&sorter/field=issuekey'
             . '&sorter/order=DESC'
             . '&sorter/field=updated'
             . '&sorter/order=DESC'
             . '&tempMax=' . $num;
        
        $client   = new Zend_Http_Client($uri);
        $response = $client->request();
        
        if (!$response->isSuccessful()) {
            return null;
        }
        
        $xml = @simplexml_load_string($response->getBody());
        
        if (!$xml) {
            return null;
        }
        
        return $xml;
    }
    
    /**
     * Report an issue
     *
     * @param  string             $name
     * @param  SimpleXMLElement   $item
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    protected function _reportIssue($name, SimpleXMLElement $item, DASBiT_Irc_Request $request = null)
    {
        $issueId   = (string) $item->key;
        $link      = (string) $item->link;
        $summary   = (string) $item->summary;
        $type      = (string) $item->type;
        $status    = (string) $item->status;
        $component = (string) $item->component;

        if (empty($component)) {
            $component = 'n/a';
        }
        
        $url = 'http://framework.zend.com/issues/browse/' . $issueId;
        
        $client = new Zend_Http_Client('http://tinyurl.com/api-create.php?url=' . $url);
        $response = $client->request();
        
        if ($response->isSuccessful()) {
            $url = $response->getBody();   
        }
            
        $client  = new Zend_Http_Client(sprintf('http://tinyurl.com/api-create.php?url=http://framework.zend.com/issues/browse/%s', $issueId));
        $tinyUrl = $client->request()->getBody();
        
        $response = sprintf('[%s:%s] [Type:%s] [Status:%s] [Component:%s] %s (See: %s)',
                            $name,
                            $issueId,
                            $type,
                            $status,
                            $component,
                            $summary,
                            $url);

        if ($request !== null) {
            $this->_client->send($response, $request);
        } else {
            $this->_client->send($response, '#zftalk.dev');
        }        
    }
}