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
 * @author  $Author$
 * @version $Revision$
 */

/**
 * @see DASBiT_Controller_Plugin_Abstract
 */
require_once 'DASBiT/Controller/Plugin/Abstract.php';

/**
 * @see Zend_Db
 */
require_once 'Zend/Db.php';

/**
 * @see ChannelsModel
 */
require_once 'ChannelsModel.php';

/**
 * @see LogsModel
 */
require_once 'LogsModel.php';

/**
 * Plugin for logging channel messages
 */
class LogPlugin extends DASBiT_Controller_Plugin_Abstract
{
    /**
     * Last year, required for log sharding
     *
     * @var integer
     */
    protected $_lastYear = null;
    
    /**
     * Log adapter to use for sharding
     *
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_logAdapter = null;
    
    /**
     * Log messages on pre dispatch
     *
     * @param  DASBiT_Controller_Request $request
     * @return void
     */
    public function preDispatch(DASBiT_Controller_Request $request)
    {
        $channelsModel = new ChannelsModel();
        $channel       = $channelsModel->fetchRow($channelsModel->select($channelsModel,
                                                                         array('channel_id'))
                                                                ->where('channel_name = ?', $request->getSource())
                                                                ->where('channel_log = 1'));                                                               
                                                                
        if ($channel !== null) {
            $now  = time();
            $year = (int) date('Y', $now);
            
            if ($year !== $this->_lastYear or $this->_logAdapter !== null) {
                $this->_logAdapter = Zend_Db::factory('pdo_sqlite',
                                                      array('dbname' => dirname(__FILE__)
                                                                        . '/../data/logs_' . $year . '.sqlite'));

                $this->_logAdapter->query('CREATE TABLE IF NOT EXISTS logs (
                                               log_id INTEGER PRIMARY KEY,
                                               channel_id INTEGER,
                                               log_timestamp INTEGER,
                                               log_nickname TEXT,
                                               log_message TEXT
                                           )');
                                                                        
                $this->_lastYear = $year;
            }
            
            $logsModel = new LogsModel(array('db' => $this->_logAdapter));
            $logsModel->insert(array('channel_id'    => $channel->channel_id,
                                     'log_timestamp' => $now,
                                     'log_nickname'  => $request->getNickname(),
                                     'log_message'   => $request->getMessage()));
        }
    }
}