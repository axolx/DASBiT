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
 * Plugin to handle Lighthouse issues
 * 
 * Currently this plugin is specific for Lighthouse
 */
class Plugin_Lighthouse extends DASBiT_Plugin {

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
        $this->_config = $this->_controller->getConfig()->lighthouse;
        $this->_controller->registerCommand($this, 'dispatch', 'lh');

        // confused why i had to add ->bin
        foreach ($this->_config->bins->bin as $bin) {
            $this->_bins[$bin->label] = $bin->query;
        }
    }

    public function dispatch(DASBiT_Irc_Request $request) {
        $words = $request->getWords();

        if (count($words == 3) && is_numeric($words[1]) && is_numeric($words[2])) {
            return $this->lookupIssue((int) $words[1], (int) $words[2], $request);
        } else if (count($words == 2) && is_string($words[1])) {
            return $this->lookupBin($words[1], $request);
        }
    }

    /**
     * Lookup a LH issue
     *
     * @param  int $projectId
     * @param  int $ticketId
     * @return void
     */
    public function lookupIssue($projectId, $issueId, $request) {

        $uri = sprintf('/projects/%d/tickets/%d.json', $projectId, $issueId);
        $json = $this->_sendRequest($uri);

        if (!$json || !isset($json->ticket)) {
            $this->_client->send('Unable to reach Lighthouse', $request);
            return;
        }

        $this->_showTicket($json->ticket, $request);
    }

    public function lookupBin($bin, DASBiT_Irc_Request $request) {
        if (!array_key_exists($bin, $this->_bins)) {
            $this->_client->send('Unknown ticket bin', $request);
            return;
        }

        $this->_controller->log($this->_bins[$bin]);
        $uri = sprintf('/tickets.json?q=%s', $this->_bins[$bin]);
        $json = $this->_sendRequest($uri);

        if (!$json || !isset($json->tickets)) {
            $this->_client->send('Unable to reach Lighthouse', $request);
            return;
        }

        $this->_listTickets($json->tickets, $request);
    }

    /**
     * Report an issue
     *
     * @param  string             $name
     * @param  string            $item
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    protected function _showTicket($data, DASBiT_Irc_Request $request = null) {

        $user = $this->_getUser($data->assigned_user_id);

        $shortUrl = DASBiT_Helpers_TinyUrl::get($data->url);

        $response = sprintf("%d %d: %s",
                        $data->project_id,
                        $data->number,
                        $data->title);
        $this->_client->send($response, $request);
        $this->_client->send("    [state]    => " . $data->state, $request);
        $this->_client->send("    [assigned] => " . $user->name, $request);
        $this->_client->send("    [opened]   => " . $data->created_at, $request);
        $this->_client->send("    [updated]  => " . $data->updated_at, $request);
        $this->_client->send("    [url]      => " . $shortUrl, $request);
    }

    protected function _listTickets($data, DASBiT_Irc_Request $request = null) {
        $out = array();
        $this->_client->send("Bin tickets:", $request);
        foreach ($data as $d) {
            $t = $d->ticket;
            $this->_client->send(sprintf("    %s %s: %s", $t->project_id, $t->number, $t->title), $request);
        }
    }

    protected function _sendRequest($uri) {
        $baseUri = sprintf('http://%s.lighthouseapp.com', $this->_config->subdomain);

        $client = new Zend_Http_Client($baseUri . $uri);

        $client->setHeaders('X-LighthouseToken', $this->_config->apiKey);
        $response = $client->request();

        if (!$response->isSuccessful()) {
            $this->_client->send('No issue with this ID found', $request);
            return;
        }

        return json_decode($response->getBody());
    }

    /**
     * Returns a user object for a user ID
     * @param int $id
     * @return stdClass
     * @todo cache
     */
    protected function _getUser($id) {
        $uri = sprintf('/users/%d.json', $id);
        $obj = $this->_sendRequest($uri);

        if (!$obj || !isset($obj->user)) {
            $this->_client->send('Unable to reach Lighthouse', $request);
            return;
        }

        return $obj->user;
    }

}