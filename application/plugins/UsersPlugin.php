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
 * @see UsersModel
 */
require_once 'UsersModel.php';

/**
 * Plugin for managing the users which can identify with DASBiT
 */
class UsersPlugin extends DASBiT_Controller_Plugin_Abstract
{
    /**
     * Users which are logged in
     *
     * @var array
     */
    protected static $_loggedInUsers = array();
      
    /**
     * Try to login with a given username and password.
     * 
     * On success, true is returned, else false.
     * 
     * @param  string                   $username Username
     * @param  string                   $password Plaintext password
     * @param  DASBiT_Controller_Requst $request  Request object which identifies the user
     * @return boolean
     */
    public static function login($username, $password, DASBiT_Controller_Request $request)
    {
        $usersModel = new UsersModel();
        $row = $usersModel->fetchRow($usersModel->select()
                                                ->where('user_name = ?',
                                                        $username));
        
        if ($row === null) {
            return false;
        } else if ($row->user_password !== sha1(md5($password))) {
            return false;
        } else {
            self::$_loggedInUsers[] = $request->getIdent();
            
            return true;
        }
    }
    
    /**
     * Check if the requesting user is allowed to perform a secured action
     *
     * @param  DASBiT_Controller_Request $request Request object which identifies the user
     * @return boolean
     */
    public static function isIdentified(DASBiT_Controller_Request $request)
    {
        if (array_search($request->getIdent(), self::$_loggedInUsers) !== false) {
            return true;
        } else {
            return false;
        }
    }
}