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
 * Set the include path
 */
set_include_path('.'
                 . PATH_SEPARATOR
                 . dirname(__FILE__) . '/library'
                 . PATH_SEPARATOR
                 . dirname(__FILE__) . '/application/plugins'
                 . PATH_SEPARATOR
                 . dirname(__FILE__) . '/application/models');

/**
 * @see Zend_Config_Xml
 */
require_once 'Zend/Config/Xml.php';

/**
 * @see Zend_Db
 */
require_once 'Zend/Db.php';

/**
 * @see Zend_Db_Table
 */
require_once 'Zend/Db/Table.php';

/**
 * @see AutoJoinPlugin
 */
require_once 'AutoJoinPlugin.php';

/**
 * @see UsersPlugin
 */
require_once 'UsersPlugin.php';

/**
 * @see SvnPlugin
 */
require_once 'SvnPlugin.php';

/**
 * @see DASBiT_Controller_Front
 */
require_once 'DASBiT/Controller/Front.php';
                 
// Get the config
$config = new Zend_Config_Xml(dirname(__FILE__) . '/config.xml', 'default');

// Setup the database
$db = Zend_Db::factory('pdo_sqlite',
                       array('dbname' => dirname(__FILE__)
                                         . '/application/data/dasbit.sqlite'));
Zend_Db_Table::setDefaultAdapter($db);

// Setup the front controller
$front = DASBiT_Controller_Front::getInstance();
$front->setControllerDirectory(dirname(__FILE__) . '/application/controllers')
      ->registerPlugin(new AutoJoinPlugin())
      ->registerPlugin(new UsersPlugin())
      ->registerPlugin(new SvnPlugin())
      ->setNickname($config->nickname)
      ->setUsername($config->username)
      ->setCommandPrefix($config->prefix)
      ->dispatch($config->server);
      