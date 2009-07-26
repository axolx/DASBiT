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

// Enable full error reporting
error_reporting(E_ALL | E_STRICT);

// Define data path
define('DATA_PATH', dirname(__FILE__) . '/data');

// Set the include path
$includePath = dirname(__FILE__) . '/library'
             . PATH_SEPARATOR
             . get_include_path();

set_include_path($includePath);

// Register the autoloader
require_once 'Zend/Loader/Autoloader.php';
$autoLoader = Zend_Loader_Autoloader::getInstance();
$autoLoader->registerNamespace('Plugin')
           ->registerNamespace('DASBiT');

// Get the config
$config = new Zend_Config_Xml(dirname(__FILE__) . '/config.xml');

// Create the IRC controller
$controller = new DASBiT_Irc_Controller($config, dirname(__FILE__) . '/Plugin');