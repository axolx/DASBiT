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
set_include_path(get_include_path()
                 . PATH_SEPARATOR
                 . dirname(__FILE__) . '/library');

/**
 * @see DASBiT_Controller_Front
 */
require_once 'DASBiT/Controller/Front.php';

/**
 * Setup the front controller
 */
$front = DASBiT_Controller_Front::getInstance();
$front->setControllerDirectory(dirname(__FILE__) . '/application/controllers')
      ->dispatch();