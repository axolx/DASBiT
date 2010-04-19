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
 * @version $Id: Channels.php 37 2009-07-26 15:38:04Z dasprid $
 */

/**
 * Plugin to handle Seen
 */
class Plugin_Seen extends DASBiT_Plugin
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
        $this->_adapter = DASBiT_Database::accessDatabase('seen', array(
            'seen' => array(
                'seen_id'   => 'INTEGER PRIMARY KEY',
                'seen_channel' => 'VARCHAR(40)',
                'seen_nickname' => 'VARCHAR(40)',
                'seen_timestamp' => 'INT(20)',
                'seen_action'	=> 'INT(1)'
            )
        ));

        $this->_controller->registerCommand($this, 'seen', 'seen');
        $this->_controller->registerHook($this, 'userJoin', 'userjoin');
        $this->_controller->registerHook($this, 'userPart', 'userpart');
        $this->_controller->registerHook($this, 'userKicked', 'userkicked');
        $this->_controller->registerHook($this, 'changedNick', 'changednick');
        $this->_controller->registerHook($this, 'channelList', 'channellist');
        $this->_controller->registerHook($this, 'userQuit', 'userquit');

    }

    /**
    * Hook called after when a user joins a channel
    *
    * @return void
    */
    public function userJoin(array $params)
    {
        $select = $this->_adapter
                       ->select()
                       ->from('seen', array('seen_nickname'))
                       ->where('seen_nickname = ?', $params['nickname'])
                       ->where('seen_channel = ?', strtolower($params['channel']));

        $user = $this->_adapter->fetchRow($select);

        if ($user == NULL) {
            $this->_adapter->insert('seen', array(
                'seen_nickname' => $params['nickname'],
                'seen_channel' => strtolower($params['channel']),
                'seen_timestamp' => time(),
                'seen_action' => 1
            ));
        } else {
            $where[] = $this->_adapter->quoteInto('seen_nickname = ?', $params['nickname']);
            $where[] = $this->_adapter->quoteInto('seen_channel = ?', strtolower($params['channel']));
            $this->_adapter->update('seen',
                            array('seen_timestamp' => time(),
                                  'seen_action' => '1'),
                                  $where);
        }
    }
    /**
    * Hook called after when a user parts a channel
    *
    * @return void
    */
    public function userPart(array $params)
    {
        $select = $this->_adapter
                       ->select()
                       ->from('seen', array('seen_nickname'))
                       ->where('seen_nickname = ?', $params['nickname'])
                       ->where('seen_channel = ?', strtolower($params['channel']));

        $user = $this->_adapter->fetchRow($select);

        if ($user == NULL) {
            $this->_adapter->insert('seen', array(
                'seen_nickname' => $params['nickname'],
                'seen_channel' => strtolower($params['channel']),
                'seen_timestamp' => time(),
                'seen_action' => 0
            ));
        } else {
            $where[] = $this->_adapter->quoteInto('seen_nickname = ?', $params['nickname']);
            $where[] = $this->_adapter->quoteInto('seen_channel = ?', strtolower($params['channel']));
            $this->_adapter->update('seen',
            array('seen_timestamp' => time(),
                  'seen_action' => '0'),
            $where);
        }
    }

    /**
    * Hook called after when a user parts a channel
    *
    * @return void
    */
    public function userKicked(array $params)
    {
        $select = $this->_adapter
                        ->select()
                        ->from('seen', array('seen_nickname'))
                        ->where('seen_nickname = ?', $params['nickname'])
                        ->where('seen_channel = ?', strtolower($params['channel']));

        $user = $this->_adapter->fetchRow($select);

        if ($user == NULL) {
            $this->_adapter->insert('seen', array(
            'seen_nickname' => $params['nickname'],
            'seen_channel' => strtolower($params['channel']),
            'seen_timestamp' => time(),
            'seen_action' => 2
            ));
        } else {
            $where[] = $this->_adapter->quoteInto('seen_nickname = ?', $params['nickname']);
            $where[] = $this->_adapter->quoteInto('seen_channel = ?', strtolower($params['channel']));
            $this->_adapter->update('seen',
            array('seen_timestamp' => time(),
                  'seen_action' => '2'),
            $where);
        }
    }

    /**
    * Hook called after when a user changes their nickname
    *
    * @return void
    */
    public function changedNick(array $params)
    {
        $where = $this->_adapter->quoteInto('seen_nickname = ?', $params['oldnickname']);
        $this->_adapter->update('seen',
                         array('seen_nickname' => $params['newnickname']),
                         $where);
    }


    /**
    * Hook called after when the bot joins a channel
    *
    * @return void
    */
    public function channelList(array $params)
    {
        $channelout = strtolower($params[4]);
        $output = array_slice($params, 6);
        foreach ($output as $userlist) {
            $nickname = ltrim($userlist, "%+@~&");
            $select = $this->_adapter
                            ->select()
                            ->from('seen',
                                array('seen_nickname'))
                            ->where('seen_nickname = ?', $nickname)
                            ->where('seen_channel = ?', $channelout);

            $user = $this->_adapter->fetchRow($select);

            if($user == NULL){
                $this->_adapter->insert('seen', array(
                    'seen_nickname' => $nickname,
                    'seen_channel' => $channelout,
                    'seen_timestamp' => time(),
                    'seen_action' => 1
                ));
            }else{
                $where[] = $this->_adapter->quoteInto('seen_nickname = ?', $nickname);
                $where[] = $this->_adapter->quoteInto('seen_channel = ?', $channelout);
                $this->_adapter->update('seen',
                array('seen_timestamp' => time(),
                      'seen_action' => '1'),
                $where);
            }

        }
    }

    /**
    * Hook called when a user quits
    *
    * @return void
    */
    public function userQuit(array $params)
    {
        $where = $this->_adapter->quoteInto('seen_nickname = ?', $params['nickname']);
        $this->_adapter->update('seen',
                         array('seen_action' => 0,
                               'seen_timestamp' => time()),
                         $where);
    }

    /**
    * Lets a user know when someone was last in the room.
    *
    * @param  DASBiT_Irc_Request $request
    * @return void
    */
    public function seen(DASBiT_Irc_Request $request)
    {
        $words = array_slice($request->getWords(), 1);

        if (count($words) !== 1) {
            $this->_client->send('Wrong number of arguments, nickname required', $request, DASBiT_Irc_Client::TYPE_NOTICE);
        }

        $select = $this->_adapter
                        ->select()
                        ->from('seen',
                            array('seen_timestamp', 'seen_action'))
                        ->where('seen_nickname LIKE ?', $words[0])
                        ->where('seen_channel = ?', strtolower($request->getSource()));

        $user = $this->_adapter->fetchRow($select);

        if ($user != NULL) {

            if ($user['seen_action'] == 1) {
                $this->_client->send($words[0].' is currently online.', $request, DASBiT_Irc_Client::TYPE_MESSAGE);
            } else {
                $this->_client->send($words[0].' was last seen on '.date(DATE_RFC822, $user['seen_timestamp']), $request);
            }

        } else {
            $this->_client->send('I have never seen '.$words[0].'.', $request);
        }
    }
}