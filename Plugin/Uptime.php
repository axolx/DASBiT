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
 * @version $Id:$
 */

/**
 * Plugin to handle Seen
 */
class Plugin_Uptime extends DASBiT_Plugin
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
        $this->_adapter = DASBiT_Database::accessDatabase('uptime', array(
            'uptime' => array(
                'uptime_timestamp' => 'INT(20)'
            )
        ));

        $this->_controller->registerCommand($this, 'uptime', 'uptime');
        $this->_controller->registerHook($this, 'setTime', 'connected');
    }

    /**
    * Hook called after bot connects to server
    *
    * @return void
    */
    public function setTime()
    {
        $select = $this->_adapter
                       ->select()
                       ->from('uptime', array('uptime_timestamp'));

        $uptime = $this->_adapter->fetchRow($select);

        if ($uptime == NULL) {
            $this->_adapter->insert('uptime', array(
                'uptime_timestamp' => time(),
            ));
        } else {
            $where[] = $this->_adapter->quoteInto('uptime_timestamp = ?', $uptime);
            $this->_adapter->update('uptime',
                            array('uptime_timestamp' => time()),
                                  $where);
        }
    }

	/**
	 * Function to calculate date or time difference.
	 * 
	 * Function to calculate date or time difference. Returns an array or
	 * false on error.
	 *
	 * @author       J de Silva                             <giddomains@gmail.com>
	 * @copyright    Copyright &copy; 2005, J de Silva
	 * @link         http://www.gidnetwork.com/b-16.html    Get the date / time difference with PHP
	 * @param        string                                 $start
	 * @param        string                                 $end
	 * @return       array
	 */
	protected function get_time_difference($start, $end)
	{
	    $uts['start']      =    $start;
	    $uts['end']        =    $end;
	    if( $uts['start']!==-1 && $uts['end']!==-1 )
	    {
		if( $uts['end'] >= $uts['start'] )
		{
		    $diff    =    $uts['end'] - $uts['start'];
		    if( $days=intval((floor($diff/86400))) )
		        $diff = $diff % 86400;
		    if( $hours=intval((floor($diff/3600))) )
		        $diff = $diff % 3600;
		    if( $minutes=intval((floor($diff/60))) )
		        $diff = $diff % 60;
		    $diff    =    intval( $diff );            
		    return( array('days'=>$days, 'hours'=>$hours, 'minutes'=>$minutes, 'seconds'=>$diff) );
		}
		else
		{
		    trigger_error( "Ending date/time is earlier than the start date/time", E_USER_WARNING );
		}
	    }
	    else
	    {
		trigger_error( "Invalid date/time data detected", E_USER_WARNING );
	    }
	    return( false );
	}



    /**
    * Lets a user know when someone was last in the room.
    *
    * @param  DASBiT_Irc_Request $request
    * @return void
    */
    public function uptime(DASBiT_Irc_Request $request)
    {
        
        $select = $this->_adapter
                        ->select()
                        ->from('uptime',
                            array('uptime_timestamp'));

        $uptime = $this->_adapter->fetchRow($select);

        if ($uptime != NULL) {
                $currtime = $this->get_time_difference($uptime['uptime_timestamp'], time());
                $this->_client->send('I have been up: ' . 
		$currtime['days'] . ' days, ' . 
		$currtime['hours'] . ' hours, ' . 
		$currtime['minutes'] . ' minutes, ' .
		$currtime['seconds'] . ' seconds', $request);

        } else {
            $this->_client->send('Error: Uptime not currently recorded.', $request);
        }
    }
}
