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
 * @version $Id: Factoids.php 47 2009-08-22 20:06:01Z dasprid $
 */

/**
 * Plugin to search the ZF manual
 */
class Plugin_Manual extends DASBiT_Plugin
{
    /**
     * Defined by DASBiT_Plugin
     *
     * @return void
     */
    protected function _init()
    {       
        $this->_controller->registerCommand($this, 'search', 'manual');
        $this->_controller->registerCommand($this, 'search', 'm');
        $this->_controller->registerTrigger($this, 'postManualLink', '#rtfm#i');
    }
       
    /**
     * Search the manual and return the first result
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function search(DASBiT_Irc_Request $request)
    {
        $searchTerms = implode(' ', array_slice($request->getWords(), 1));

        if (preg_match('(^(.*) for ([A-Za-z\[\]\\\\`_^{|}][A-Za-z\[\]\\\\`_^{|}\-0-9]*)$)', $searchTerms, $matches)) {
            $searchTerms = $matches[1];
            $targetUser  = $matches[2];
        } else {
            $targetUser = $request->getNickname();
        }

        $client = new Zend_Http_Client();
        $client->setParameterGet('v', '1.0')
               ->setParameterGet('q', $searchTerms . ' site:http://framework.zend.com/manual/en/')
               ->setUri('http://ajax.googleapis.com/ajax/services/search/web')
               ->setHeaders('Referer', 'http://dasbit.dasprids.de');

        $result = $client->request();

        if (!$result->isSuccessful()) {
            $this->_client->send('An error occured while querying the search engine', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }

        $data = json_decode($result->getBody());

        if ($data === null) {
            $this->_client->send('An error occured while processing the result', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        } elseif (!isset($data->responseData->results[0])) {
            $this->_client->send('Nothing found', $request);
            return;
        }

        $result = $data->responseData->results[0];

        $this->_client->send($targetUser . ', see ' . $result->url, $request);
    }

    /**
     * Respond with the manual link when somebody says 'rtfm'
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function postManualLink(DASBiT_Irc_Request $request)
    {
        $this->_client->send('http://framework.zend.com/manual/en/', $request);
    }
}