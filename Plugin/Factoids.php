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
 * Plugin to handle Factoids
 */
class Plugin_Factoids extends DASBiT_Plugin
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
        $this->_adapter = DASBiT_Database::accessDatabase('factoids', array(
            'factoids' => array(
                'factoid_id'          => 'INTEGER PRIMARY KEY',
                'factoid_name'        => 'VARCHAR(128)',
                'factoid_description' => 'TEXT',
            )
        ));
        
        $this->_controller->registerCommand($this, 'add', 'factoid add');
        $this->_controller->registerCommand($this, 'remove', 'factoid remove');
        $this->_controller->registerCommand($this, 'tell', 'tell');
    }
       
    /**
     * Add factoid
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function add(DASBiT_Irc_Request $request)
    {
        if (!Plugin_Users::isIdentified($request)) {
            $this->_client->send('You must be identified to add factoids', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        if (preg_match('#^.*?factoid add (.*?) => (.*)$#', $request->getMessage(), $matches) === 0) {
            $this->_client->send('Wrong syntax, use "factoid add name => description"', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $select = $this->_adapter
                       ->select()
                       ->from('factoids',
                              array('factoid_id'))
                       ->where('factoid_name = ?', $matches[1]);
                       
        $factoid = $this->_adapter->fetchRow($select);
        
        if ($factoid === false) {
            $this->_adapter->insert('factoids', array(
                'factoid_name'        => $matches[1],
                'factoid_description' => $matches[2]
            ));
        } else {
            $this->_adapter->update('factoids', array(
                'factoid_description' => $matches[2]
            ), 'factoid_id = ' . $factoid['factoid_id']);
        }
        
        $this->_client->send('Factoid added', $request, DASBiT_Irc_Client::TYPE_NOTICE);
    }

    /**
     * Remove factoid
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function remove(DASBiT_Irc_Request $request)
    {
        if (!Plugin_Users::isIdentified($request)) {
            $this->_client->send('You must be identified to remove factoids', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $words = array_slice($request->getWords(), 2);
        
        if (count($words) === 0) {
            $this->_client->send('No factoid name given', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $select = $this->_adapter
                       ->select()
                       ->from('factoids',
                              array('factoid_id'))
                       ->where('factoid_name = ?', $implode(' ', $words));
                       
        $factoid = $this->_adapter->fetchRow($select);
        
        if ($factoid === false) {
            $this->_client->send('There is no factoid with this name', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $this->_adapter->delete('factoids', $this->_adapter->quoteInto('factoid_id = ?', $factoid['factoid_id']));
        
        $this->_client->send('Factoid removed', $request, DASBiT_Irc_Client::TYPE_NOTICE);
    }
    
    /**
     * Tell about a factoid
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function tell(DASBiT_Irc_Request $request)
    {
        $words = array_slice($request->getWords(), 1);
        
        if (count($words) === 0) {
            $this->_client->send('Too less arguments', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        if ($words[0] === 'about') {
            $nickname    = null;
            $factoidName = implode(' ', array_slice($words, 1));
        } elseif ($words[1] === 'about') {
            $nickname    = $words[0];
            $factoidName = implode(' ', array_slice($words, 2));            
        } else {
            $this->_client->send('Wrong syntax, use "tell (nickname) about factoid"', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;            
        }
        
        $select = $this->_adapter
                       ->select()
                       ->from('factoids',
                              array('factoid_description'))
                       ->where('factoid_name = ?', $factoidName);
                       
        $factoid = $this->_adapter->fetchRow($select);
        
        if ($factoid === false) {
            $this->_client->send('There is no factoid with this name', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        if ($nickname === null) {
            $this->_client->send($factoid['factoid_description'], $request);
        } else {
            $this->_client->send($nickname . ', ' . $factoid['factoid_description'], $request);
        }
    }
}