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
 * @see SvnModel
 */
require_once 'SvnModel.php';

/**
 * Plugin for managing the users which can identify with DASBiT
 */
class SvnPlugin extends DASBiT_Controller_Plugin_Abstract
{   
    /**
     * Delay time for SVN checks
     *
     * @var integer
     */
    protected $_delayTime = 0;
           
    /**
     * Check every 2 mintues for svn changes
     *
     * @return void
     */
    public function delayedCycle()
    {
        if ($this->_delayTime + 60 * 2 <= time()) {
            $response     = DASBiT_Controller_Response::getInstance();
            $svnModel     = new SvnModel();
            $repositories = $svnModel->fetchAll();
            
            foreach ($repositories as $repository) {
                $lastRev    = (int) $repository->svn_last_rev;
                $infoResult = shell_exec('svn info ' . $repository->svn_url);
                if (preg_match('#Revision: ([0-9]+)#', $infoResult, $match) === 1) {
                    $currentRev = (int) $match[1];
    
                    if ($currentRev > $lastRev) {
                        $logs = $this->_parseSvnLog($repository->svn_url,
                                                    ($lastRev + 1),
                                                    $currentRev);
    
                        foreach ($logs as $log) {
                            $message = '[SVN:r' . $log['revision'] . ':' . $log['username'] . '] ' . $log['content'];
                            
                            if ($repository->svn_link !== 'NULL') {
                                $link = str_replace('%R%', $log['revision'], $repository->svn_link);
                                
                                $message .= ' (See: ' . $link . ')';
                            }
                            
                            $response->send($message, $repository->svn_channel);
                        }
    
                        $repository->svn_last_rev = $currentRev;
                        $repository->save();
                    }
                }
            }

            $this->_delayTime = time();
        }
    }
    
    /**
     * Parse SVN log
     *
     * @param  integer $fromRev From which revision
     * @param  integer $toRev   To which revision
     * @return array
     */
    protected function _parseSvnLog($url, $fromRev, $toRev)
    {
        $logResults = explode("\n", shell_exec('svn log -r' . $fromRev . ':' . $toRev . ' ' . $url));
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