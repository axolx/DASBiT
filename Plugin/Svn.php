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
 * Plugin to handle SVN information
 */
class Plugin_Svn extends DASBiT_Plugin
{
    /**
     * Latest reported revision
     *
     * @var integer
     */
    protected $_latestRevision;
    
    /**
     * Defined by DASBiT_Plugin
     *
     * @return void
     */
    protected function _init()
    {
        $latestCommit = $this->_getCommits('HEAD');
        
        if (count($latestCommit) > 0) { 
            $this->_latestRevision = $latestCommit[0]['revision'];
        }
        
        $this->_controller->registerInterval($this, 'watchUpdates', 120);
    }
    
    /**
     * Watch for updates
     *
     * @return void
     */
    public function watchUpdates()
    {
        $client  = new Zend_Http_Client();
        $commits = $this->_getCommits(($this->_latestRevision + 1) . ':HEAD');
        
        foreach ($commits as $commit) {
            $url = 'http://framework.zend.com/code/changelog/Zend_Framework/?cs=' . $commit['revision'];
            
            $client->setUri('http://tinyurl.com/api-create.php?url=' . $url);
            $response = $client->request();
            
            if ($response->isSuccessful()) {
                $url = $response->getBody();   
            }
            
            $response = sprintf('[SVN:r%d:%s] %s (See: %s)',
                                $commit['revision'],
                                $commit['username'],
                                $commit['message'],
                                $url);

            $this->_client->send($response, $request);
            
            $this->_latestRevision = $commit['revision'];
        }
    }
    
    /**
     * Get commits according to the given range
     *
     * @param  string $range
     * @return array
     */
    protected function _getCommits($range)
    {
        $logResults = explode("\n", shell_exec('svn log -r' . $range . ' http://framework.zend.com/svn/framework'));
        $commits    = array();

        foreach ($logResults as $totalLineNum => $content) {
            // Skip the first line
            if ($totalLineNum === 0) {
                continue;
            }

            if (isset($commit) === false) {
                if (preg_match('#r([0-9]+) [|] (.*?) [|] (.*?) [|] ([0-9])+ lines?#', $content, $match) === 0) {
                    continue;
                }

                $commit = array('revision' => (int) $match[1],
                                'username' => $match[2],
                                'content'  => '');

                $numLines    = (int) $match[4];
                $currentLine = -1;
            } else {
                // Skip the first comment line, as it is always empty
                if ($currentLine++ === -1) {
                    continue;
                }

                $commit['content'] .= trim($content) . "\n";

                if ($currentLine == $numLines) {
                    $commit['content'] = str_replace("\n", ' | ', trim($commit['content']));

                    if (count($commit['content']) === 0) {
                        $commit['content']= 'No log message';
                    }

                    $commits[] = $commit;
                    unset($commit);
                }
            }
        }
        
        return $commits;
    }
}