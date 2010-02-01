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
            ),
            'aliases' => array(
                'factoid_id' => 'INTEGER PRIMARY KEY',
                'alias_from' => 'VARCHAR(128)',
                'alias_to'   => 'TEXT',
            ),
        ));
        
        $this->_controller->registerCommand($this, 'addFactoid', 'factoid add');
        $this->_controller->registerCommand($this, 'removeFactoid', 'factoid remove');
        $this->_controller->registerCommand($this, 'addAlias', 'alias add');
        $this->_controller->registerCommand($this, 'removeAlias', 'alias remove');
        $this->_controller->registerCommand($this, 'tell', 'tell');
        $this->_controller->registerTrigger($this, 'tell', '.');
    }
       
    /**
     * Add factoid
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function addFactoid(DASBiT_Irc_Request $request)
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
                       ->where('factoid_name = ?', strtolower($matches[1]));
                       
        $factoid = $this->_adapter->fetchRow($select);
        
        if ($factoid === false) {
            $this->_adapter->insert('factoids', array(
                'factoid_name'        => strtolower($matches[1]),
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
    public function removeFactoid(DASBiT_Irc_Request $request)
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
                       ->where('factoid_name = ?', strtolower(implode(' ', $words)));
                       
        $factoid = $this->_adapter->fetchRow($select);
        
        if ($factoid === false) {
            $this->_client->send('There is no factoid with this name', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $this->_adapter->delete('factoids', $this->_adapter->quoteInto('factoid_id = ?', $factoid['factoid_id']));
        
        $this->_client->send('Factoid removed', $request, DASBiT_Irc_Client::TYPE_NOTICE);
    }

    /**
     * Add alias
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function addAlias(DASBiT_Irc_Request $request)
    {
        if (!Plugin_Users::isIdentified($request)) {
            $this->_client->send('You must be identified to add aliases', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }

        if (preg_match('#^.*?alias add (.*?) => (.*)$#', $request->getMessage(), $matches) === 0) {
            $this->_client->send('Wrong syntax, use "alias add from => to"', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }

        $select = $this->_adapter
                       ->select()
                       ->from('aliases',
                              array('alias_id'))
                       ->where('alias_from = ?', strtolower($matches[1]));

        $alias = $this->_adapter->fetchRow($select);

        if ($alias === false) {
            $this->_adapter->insert('aliases', array(
                'alias_from' => strtolower($matches[1]),
                'alias_to'   => $matches[2]
            ));
        } else {
            $this->_adapter->update('aliases', array(
                'alias_to' => $matches[2]
            ), 'alias_id = ' . $alias['alias_id']);
        }

        $this->_client->send('Alias added', $request, DASBiT_Irc_Client::TYPE_NOTICE);
    }

    /**
     * Remove alias
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function removeAlias(DASBiT_Irc_Request $request)
    {
        if (!Plugin_Users::isIdentified($request)) {
            $this->_client->send('You must be identified to remove aliases', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }

        $words = array_slice($request->getWords(), 2);

        if (count($words) === 0) {
            $this->_client->send('No alias name given', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }

        $select = $this->_adapter
                       ->select()
                       ->from('aliases',
                              array('alias_id'))
                       ->where('alias_from = ?', strtolower(implode(' ', $words)));

        $alias = $this->_adapter->fetchRow($select);

        if ($factoid === false) {
            $this->_client->send('There is no alias with this name', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }

        $this->_adapter->delete('aliases', $this->_adapter->quoteInto('alias_id = ?', $alias['alias_id']));

        $this->_client->send('Alias removed', $request, DASBiT_Irc_Client::TYPE_NOTICE);
    }
    
    /**
     * Tell about a factoid
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function tell(DASBiT_Irc_Request $request)
    {
        $command = array_slice($request->getWords(), 0, 1);

        if (!preg_match('(tell$)', $command)) {
            $nickname    = $request->getNickname();
            $factoidName = trim($request->getMessage());
            $reaction    = true;
        } else {
            $reaction = false;
            $words    = array_slice($request->getWords(), 1);

            if (count($words) < 2) {
                $this->_client->send('Too less arguments', $request, DASBiT_Irc_Client::TYPE_NOTICE);
                return;
            }

            if ($words[1] === 'about') {
                $nickname    = $words[0];
                $factoidName = trim(implode(' ', array_slice($words, 2)));
            } elseif ($words[0] === 'about') {
                $nickname    = null;
                $factoidName = trim(implode(' ', array_slice($words, 1)));
            } else {
                $this->_client->send('Wrong syntax, use "tell (nickname) about factoid"', $request, DASBiT_Irc_Client::TYPE_NOTICE);
                return;
            }
        }
        
        $select = $this->_adapter
                       ->select()
                       ->from('factoids',
                              array('factoid_description'))
                       ->where('factoid_name = ?', strtolower($factoidName));
                       
        $factoid = $this->_adapter->fetchRow($select);

        if ($factoid === false) {
            $select = $this->_adapter
                           ->select()
                           ->from('aliases',
                                  array('alias_to'))
                           ->where('alias_from = ?', strtolower($factoidName));

            $alias = $this->_adapter->fetchRow($select);

            if ($alias !== false) {
                $select = $this->_adapter
                               ->select()
                               ->from('factoids',
                                      array('factoid_description'))
                               ->where('factoid_name = ?', strtolower($alias['alias_to']));

                $factoid = $this->_adapter->fetchRow($select);
            }
        }

        if ($factoid === false) {
            if (!$reaction) {
                $this->_client->send('There is no factoid or alias with this name', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            }
            return;
        }
        
        if ($nickname === null) {
            $this->_client->send($factoid['factoid_description'], $request);
        } else {
            $this->_client->send($nickname . ', ' . $factoid['factoid_description'], $request);
        }
    }
}