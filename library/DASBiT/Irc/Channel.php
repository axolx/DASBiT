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
 * IRC Channel class
 */
class DASBiT_Irc_Channel
{
    /**
     * Users in this channel
     *
     * @var array
     */
    protected $_users = array();
    
    /**
     * Name of the channel
     *
     * @var string
     */
    protected $_name;
    
    /**
     * Create a channel
     *
     * @param string $name Name of the channel
     */
    public function __construct($name)
    {
        $this->_name = $name;
    }
    
    /**
     * Add a user to the channel
     * @param  string $nickname Nickname of the user
     * @return void
     */
    public function addUser($nickname)
    {
        $this->_users[] = $nickname;
    }

    /**
     * Remove a user from the channel
     * 
     * @param  string $nickname Nickname of the user
     * @return void
     */
    public function removeUser($nickname)
    {
        $this->_users = array_diff($this->_users, array($nickname));
    }
    
    /**
     * Get the name of the channel
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }
    
    /**
     * Get all users
     *
     * @return array
     */
    public function getUsers()
    {
        return $this->_users;
    }
    
    /**
     * Count number of users in the channel
     *
     * @return integer
     */
    public function countUsers()
    {
        return count($this->_users);
    }
}