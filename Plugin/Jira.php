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
 */
class Plugin_Jira extends DASBiT_Plugin
{
    /**
     * Defined by DASBiT_Plugin
     *
     * @return void
     */
    protected function _init()
    {
        $this->_controller->registerCommand($this, 'lookupIssue', 'issue');
        $this->_controller->registerInterval($this, 'watchUpdates', 120);
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
        $uri     = sprintf('http://framework.zend.com/issues/si/jira.issueviews:issue-xml/%1$s/%1$s.xml', urlencode($issueId));
        
        $client   = new Zend_Http_Client($uri);
        $response = $client->request();

        if (!$response->isSuccessful()) {
            $this->_client->send('No issue with this ID found', $request);
            return;
        }
        
        $xml  = simplexml_load_string($response->getBody());
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
        //$this->_reportIssue('Issue-Update', $item, $request);
    }
    
    /**
     * Report an issue
     *
     * @param  string             $name
     * @param  SimpleXMLElement   $item
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    protected function _reportIssue($name, SimpleXMLElement $item, DASBiT_Irc_Request $request)
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
                            
        $this->_client->send($response, $request);        
    }
}