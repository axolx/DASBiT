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
 * Plugin to handle Channels
 */
class Plugin_Channels extends DASBiT_Plugin
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
        $this->_adapter = DASBiT_Database::accessDatabase('channels', array(
            'channels' => array(
                'channel_id'   => 'INTEGER PRIMARY KEY',
                'channel_name' => 'VARCHAR(40)'
            )
        ));
        
        $this->_controller->registerCommand($this, 'join', 'join');
        $this->_controller->registerCommand($this, 'part', 'part');
        $this->_controller->registerHook($this, 'connected', 'connected');
    }
    
    /**
     * Hook called after connect
     *
     * @return void
     */
    public function connected()
    {
        $select = $this->_adapter
                       ->select()
                       ->from('channels',
                              array('channel_name'));
                              
        $channels = $this->_adapter->fetchAll($select);
        
        foreach ($channels as $channel) {
            $this->_client->sendRaw('JOIN ' . $channel['channel_name']);
			$this->_controller->triggerHook('channeljoined', array('channel' => $channel['channel_name']));
        }
    }
    
    /**
     * Join a channel
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function join(DASBiT_Irc_Request $request)
    {
        if (!Plugin_Users::isIdentified($request)) {
            $this->_client->send('You must be identified to join channels', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $words = array_slice($request->getWords(), 1);
        
        if (count($words) !== 1) {
            $this->_client->send('Wrong number of arguments, channel required', $request, DASBiT_Irc_Client::TYPE_NOTICE);
        }
        
        $this->_adapter->insert('channels', array(
            'channel_name' => $words[0]
        ));
        
        $this->_client->sendRaw('JOIN ' . $words[0]);
    }

    /**
     * Part a channel
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function part(DASBiT_Irc_Request $request)
    {
        if (!Plugin_Users::isIdentified($request)) {
            $this->_client->send('You must be identified to part channels', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $words = array_slice($request->getWords(), 1);
        
        if (count($words) === 1) {
            $channel = $words[0];
        } elseif (count($words) === 0) {
            if ($request->getSource() === $request->getNickname()) {
                $this->_client->send('Part without arguments only allowed in channel', $request, DASBiT_Irc_Client::TYPE_NOTICE);
                return;
            }
            
            $channel = $request->getSource();
        } else {
            $this->_client->send('Wrong number of arguments, channel or none required', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $this->_adapter->delete('channels', $this->_adapter->quoteInto('channel_name = ?', $channel));
        
        $this->_client->sendRaw('PART ' . $channel);
    }
}