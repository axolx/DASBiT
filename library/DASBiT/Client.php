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

class DASBiT_Irc_Client
{
    /**
     * Address of the server
     *
     * @var string
     */
    protected $_address;
    
    /**
     * Port of the server
     *
     * @var integer
     */
    protected $_port;
    
    /**
     * Create a new client connection to a server
     *
     * @param string  $hostname
     * @param integer $port
     */
    public function __construct($hostname, $port = 6777)
    {
        $address = gethostbyname($hostname);
        
        if (ip2long($address) === false || ($address === gethostbyaddr($address)
            && preg_match("#.*\.[a-zA-Z]{2,3}$#", $host) === 0) )
        {
           throw new DASBiT_Irc_Exception('Hostname is not valid');
        }
        
        $this->_address = $address;
    }
    
    
}