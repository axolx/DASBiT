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
 * @version $Id: Factoids.php 67 2010-02-01 01:24:02Z dasprid $
 */

/**
 * Plugin to handle logging
 */
class Plugin_Log extends DASBiT_Plugin
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
        $this->_adapter = DASBiT_Database::accessDatabase('logs', array(
            'logs' => array(
                'log_id'        => 'INTEGER PRIMARY KEY',
                'log_channel'   => 'VARCHAR(128)',
                'log_timestamp' => 'INTEGER',
                'log_user'      => 'TEXT',
                'log_message'   => 'TEXT'
            )
        ));
        
        $this->_controller->registerTrigger($this, 'log', '#.#');
    }
       
    /**
     * Log message
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function log(DASBiT_Irc_Request $request)
    {
        if ($request->getNickname() === $request->getSource()) {
            return;
        }

        $this->_adapter->insert('logs', array(
            'log_channel'   => $request->getSource(),
            'log_timestamp' => time(),
            'log_nickname'  => $request->getNickname(),
            'log_message'   => $request->getMessage()
        ));
    }
}