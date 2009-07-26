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

/**
 * Database handler and synchronizer
 */
class DASBiT_Database
{
    /**
     * Access a database, create or synchronize it in case
     * 
     * $tables should be an array containing all tables with their column
     * definitions. 
     *
     * @param  string $name
     * @param  array  $tables
     * @return Zend_Db
     */
    public static function accessDatabase($name, array $tables)
    {
        // Create or read the databse file
        $path    = DATA_PATH . '/' . $name . '.sqlite';
        $adapter = Zend_Db::factory('pdo_sqlite', array('dbname' => $path));
        
        // Get all existent tables
        $rows           = $adapter->fetchAll("SELECT name, sql FROM sqlite_master WHERE type = 'table'");
        $existentTables = array();
        
        foreach ($rows as $row) {
            if (preg_match('#CREATE TABLE ' . preg_quote($row['name'], '#') . ' \((.*)\)#s', $row['sql'], $matches) === 0) {
                throw new DASBiT_Exception('Incompatible table definition found');
            }

            $columns       = explode(',', $matches[1]);
            $parsedColumns = array();
            
            foreach ($columns as $column) {
                if (preg_match('#^(.*?)(?: (.*))?$#', trim($column), $matches) === 0) {
                    throw new DASBiT_Exception('Incompatible column definition found');
                }

                $parsedColumns[$matches[1]] = isset($matches[2]) ? $matches[2] : '';
            }
            
            $existentTables[$row['name']] = $parsedColumns;
        }
        
        // Check for tables to insert or update
        foreach ($tables as $tableName => $columns) {
            if (!isset($existentTables[$tableName])) {
                // Insert table
                $columnStrings = array();
                
                foreach ($columns as $columnName => $type) {
                    $columnStrings[] = $columnName . ' ' . $type;
                }
                
                $adapter->query(sprintf('CREATE TABLE %s (%s)', $tableName, implode(', ', $columnStrings)));
            } else {
                // Check for new or changed columns
                foreach ($columns as $columnName => $type) {
                    if (!isset($existentTables[$tableName][$columnName])) {
                        $adapter->query(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $tableName, $columnName, $type));
                    } elseif ($existentTables[$tableName][$columnName] !== $type) {
                        throw new DASBiT_Exception('A column-definition was changed in schema, which is not supported by SQLITE');
                    }
                }
            }
        }
        
        // Check for tables to delete
        foreach ($existentTables as $tableName => $columns) {
           if (!isset($tables[$tableName])) {
                // Delete table                
                $adapter->query(sprintf('DROP TABLE %s', $tableName));
            } else {
                // Check for columns to delete
                foreach ($columns as $columnName => $type) {
                    if (!isset($tables[$tableName][$columnName])) {
                        throw new DASBiT_Exception('A column was deleted in schema, which is not supported by SQLITE');
                    }
                }
            }
        }
        
        // Return the adapter
        return $adapter;
    }
}