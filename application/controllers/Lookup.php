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
 * @see DASBiT_Controller_Action_Interface
 */
require_once 'DASBiT/Controller/Action/Interface.php';

/**
 * Controller for looking up documentation
 */
class Lookup_Controller implements DASBiT_Controller_Action_Interface
{
    /**
     * Defined by DASBiT_Controller_Action_Interface
     *
     * @param  DASBiT_Controller_Request  $request
     * @param  DASBIT_Controller_Response $response
     * @return void
     */
    public function dispatch(DASBiT_Controller_Request $request, DASBIT_Controller_Response $response)
    {
        $message = $request->getMessage();
        $params  = array_slice(explode(' ', $message), 1);
        
        if ($params[0][0] === '@') {
            $prefix      = substr($params[0], 1) . ', see: ';
            $searchTerms = trim(implode(' ', array_slice($params, 1)));    
        } else {
            $prefix      = '';
            $searchTerms = trim(implode(' ', $params));
        }
        
        $searchTerms = preg_replace('#[*]#', '', $searchTerms);

        if (empty($searchTerms) === false) {
            $result = file_get_contents('http://framework.zend.com/manual/'
                                        . 'search?query='
                                        . urlencode($searchTerms)
                                        . '&language=en&search=Search+Manual%21');
        } else {
            $result = '';
        }

        if (preg_match('#<li><a href="(/manual/en/.*?)">(.*?)  \\[en\\]</a></li>#', $result, $matches) === 1) {
            $response->send($prefix . trim($matches[2]) . ': http://framework.zend.com' . trim($matches[1]), $request);
        } else {
            $response->send('Nothing found', $request);
        }
    }

    /**
     * Defined by DASBiT_Controller_Action_Interface
     *
     * @return string
     */
    public function getHelp()
    {
        return '<search terms>';
    }
}