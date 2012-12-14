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
 * Plugin to handle Redmine issues
 *
 * Currently this plugin is specific for Redmine
 */
class Plugin_Redmine extends DASBiT_Plugin {

    /**
     * Plugin config
     *
     * @var Zend_Config
     */
    protected $_config;
    /**
     * Bins
     *
     * @var array
     */
    protected $_bins = array();

    /**
     * Defined by DASBiT_Plugin
     *
     * @return void
     */
    protected function _init() {
        $this->_config = $this->_controller->getConfig()->redmine;
        $this->_controller->registerCommand($this, 'dispatch', 'rm');
        $this->_controller->registerTrigger($this, 'dispatchTrigger', '/(#.\d+)/');
    }

    public function dispatch(DASBiT_Irc_Request $request) {
        $words = $request->getWords();

        if (is_numeric($words[1])) {
            return $this->lookupIssue((int) $words[1], $request);
        }
    }

    public function dispatchTrigger(DASBiT_Irc_Request $request) {

        if (preg_match('/#(\d+)/', $request->getMessage(), $matches)) {
          if (is_numeric($matches[1])) {
              return $this->lookupIssue((int) $matches[1], $request);
          }
        }

    }

    /**
     * Lookup a Redmine issue
     *
     * @param  int $ticketId
     * @return void
     */
    public function lookupIssue($issueId, $request) {

        $uri = sprintf('/issues/%d.json', $issueId);
        $ticket = $this->_sendRequest($uri, $request);

        if (!$ticket || !isset($ticket->issue)) {
            return;
        }

        $this->_showTicket($ticket->issue, $request);
    }

    /**
     * Report an issue
     *
     * @param  object            $data
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    protected function _showTicket($data, DASBiT_Irc_Request $request = null) {

        $response = sprintf("%s => %s => %s [%s]",
                        $data->project->name,
                        $data->subject,
                        $this->_config->domain . 'issues/' . $data->id,
                        $data->status->name);
        $this->_client->send($response, $request);
    }

    protected function _sendRequest($uri, DASBiT_Irc_Request $request = null) {
        $baseUri = $this->_config->domain;

        $client = new Zend_Http_Client($baseUri . $uri);

        $client->setHeaders('X-Redmine-API-Key', $this->_config->apiKey);
        $client->setHeaders('Content-Type', 'application/json');
        $response = $client->request();

        if (!$response->isSuccessful()) {
            $this->_client->send('Unable to connnect to Redmine.', $request);
            return;
        }

        return json_decode($response->getBody());
    }
}
