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
 * Plugin to handle Users
 */
class Plugin_Users extends DASBiT_Plugin
{
    /**
     * Database adapter
     *
     * @var Zend_Db_Adapter_Pdo_Sqlite
     */
    protected $_adapter;
    
    /**
     * Users which are identified to the bot
     *
     * @var array
     */
    protected static $_identified = array();
    
    /**
     * Defined by DASBiT_Plugin
     *
     * @return void
     */
    protected function _init()
    {
        $this->_adapter = DASBiT_Database::accessDatabase('users', array(
            'users' => array(
                'user_id'       => 'INTEGER PRIMARY KEY',
                'user_name'     => 'VARCHAR(40)',
                'user_password' => 'VARCHAR(40)'
            )
        ));
        
        $this->_controller->registerCommand($this, 'add', 'user add');
        $this->_controller->registerCommand($this, 'remove', 'user remove');
        $this->_controller->registerCommand($this, 'identify', 'identify');
    }
    
    /**
     * Add a user
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function add(DASBiT_Irc_Request $request)
    {
        $words = array_slice($request->getWords(), 2);
        
        if (count($words) !== 2) {
            $this->_client->send('Wrong number of arguments, username and password required', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $select = $this->_adapter
                       ->select()
                       ->from('users',
                              array('user_id'))
                       ->where('user_name = ?', $words[0]);
                       
        $user = $this->_adapter->fetchRow($select);

        if ($user !== false) {
            $this->_client->send('User already exists', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;            
        }
        
        $this->_adapter->insert('users', array(
            'user_name'     => $words[0],
            'user_password' => sha1($words[1])
        ));
        
        $this->_client->send('User added', $request, DASBiT_Irc_Client::TYPE_NOTICE);
    }
    
    /**
     * Remove a user
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function remove(DASBiT_Irc_Request $request)
    {
        $words = array_slice($request->getWords(), 2);
        
        if (count($words) !== 1) {
            $this->_client->send('Wrong number of arguments, username required', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $this->_adapter->delete('users', $this->_adapter->quoteInto('user_name = ?', $words[0]));
        unset(self::$_identified[$words[0]]);
        
        $this->_client->send('User removed', $request, DASBiT_Irc_Client::TYPE_NOTICE);
    }

    /**
     * Identify to the bot
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function identify(DASBiT_Irc_Request $request)
    {
        $words = array_slice($request->getWords(), 1);
        
        if (count($words) !== 2) {
            $this->_client->send('Wrong number of arguments, username and password required', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $select = $this->_adapter
                       ->select()
                       ->from('users',
                              array('user_name',
                                    'user_password'))
                       ->where('user_name = ?', $words[0]);
                       
        $user = $this->_adapter->fetchRow($select);

        if ($user === false) {
            $this->_client->send('Username does not exist', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;            
        } elseif ($user['user_password'] !== sha1($words[1])) {
            $this->_client->send('Password is not correct', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        } else {
            self::$_identified[$user['user_name']] = $request->getIdent();
            
            $this->_client->send('You are now identified', $request, DASBiT_Irc_Client::TYPE_NOTICE);
        }
    }
    
    /**
     * Check if the user of a request is identified
     *
     * @param  DASBiT_Irc_Request $request
     * @return boolean
     */
    public static function isIdentified(DASBiT_Irc_Request $request)
    {
        return array_search($request->getIdent(), self::$_identified, true);
    }
}