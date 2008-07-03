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
 * @see DASBiT_Log_Interface
 */
require_once 'DASBiT/Log/Interface.php';

/**
 * Log combiner
 */
class DASBiT_Log_Combine implements DASBiT_Log_Interface
{
    /**
     * All loggers of the combiner
     *
     * @var array
     */
    protected $_loggers = array();
    
    /**
     * Defined by DASBiT_Log_Interface
     * 
     * @param  string $message
     * @return void
     */
    public function log($message)
    { 
        foreach ($this->_loggers as $logger) {
            $logger->log($message);
        }
    }
    
    /**
     * Add a logger to the combiner
     *
     * @param  DASBiT_Log_Interface $logger
     * @return DASBiT_Log_Combine
     */
    public function addLogger(DASBiT_Log_Interface $logger)
    {
        $this->_loggers[] = $logger;
    }
}
