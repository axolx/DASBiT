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
 * Client class for IRC servers
 */
class DASBiT_Helpers_TinyUrl
{
    /**
     * Get a TinyUrl from a URI
     * @param string $uri
     * @return string
     */
    static function get($uri)
    {
        $client = new Zend_Http_Client('http://tinyurl.com/api-create.php?url=' . $uri);
        $response = $client->request();
        if ($response->isSuccessful()) {
            return $response->getBody();
        }
        else {
            throw new Exception('Unable to get a TinyUrl');
        }
    }
}

?>