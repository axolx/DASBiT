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
     * Database adapter
     *
     * @var Zend_Db_Adapter_Pdo_Sqlite
     */
    protected $_adapter;
    
    /**
     * Defined by DASBiT_Plugin
     *
     * @return void
     */
    protected function _init()
    {
        $this->_adapter = DASBiT_Database::accessDatabase('svn', array(
            'repositories' => array(
                'repos_id'            => 'INTEGER PRIMARY KEY',
                'repos_last_revision' => 'NUMBER',
                'repos_channel'       => 'VARCHAR(40)',
                'repos_url'           => 'VARCHAR(255)',
                'repos_info_url'      => 'VARCHAR(255)'
            )
        ));
        
        $select = $this->_adapter
                       ->select()
                       ->from('repositories',
                              array('repos_id',
                                    'repos_url'));
                              
        $repositories = $this->_adapter->fetchAll($select);
        
        foreach ($repositories as $repository) {
            $latestCommit = $this->_getCommits($repository['repos_url'], 'HEAD');

            if (count($latestCommit) > 0) {
                $lastRevision = $latestCommit[0]['revision'];
            } else {
                $lastRevision = -1;
            }
            
            $this->_adapter->update('repositories',
                                    array('repos_last_revision' => $lastRevision),
                                    'repos_id = ' . ($repository['repos_id'])); 
        }

        $this->_controller->registerCommand($this, 'add', 'svn add');
        $this->_controller->registerCommand($this, 'remove', 'svn remove');
        $this->_controller->registerInterval($this, 'watchUpdates', 120);
    }
    
    /**
     * Add SVN repository
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function add(DASBiT_Irc_Request $request)
    {
        if (!Plugin_Users::isIdentified($request)) {
            $this->_client->send('You must be identified to add a repository', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $words = array_slice($request->getWords(), 2);

        if (count($words) < 2) {
            $this->_client->send('Not enough arguments, channel and url required', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $infoUrl      = (count($words) === 3) ? $words[2] : '';
        $latestCommit = $this->_getCommits($words[1], 'HEAD');
        
        if (count($latestCommit) === 0) {
            $this->_client->send('Could not access SVN repository', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $this->_adapter->insert('repositories', array(
            'repos_channel'       => $words[0],
            'repos_url'           => $words[1],
            'repos_info_url'      => $infoUrl,
            'repos_last_revision' => $latestCommit[0]['revision']
        ));
        
        $this->_client->send('Repository added', $request, DASBiT_Irc_Client::TYPE_NOTICE);
    }

    /**
     * Remove SVN repository
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function remove(DASBiT_Irc_Request $request)
    {
        if (!Plugin_Users::isIdentified($request)) {
            $this->_client->send('You must be identified to remove a repository', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $words = array_slice($request->getWords(), 2);
        
        if (count($words) < 2) {
            $this->_client->send('Not enough arguments, channel and url required', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $select = $this->_adapter
                       ->select()
                       ->from('repositories',
                              array('repos_id'))
                       ->where('repos_channel = ?', $words[0])
                       ->where('repos_url = ?', $words[1]);
                       
        $repository = $this->_adapter->fetchRow($select);
        
        if ($repository === false) {
            $this->_client->send('There is no repository with this channel and URL', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }
        
        $this->_adapter->delete('repositories', $this->_adapter->quoteInto('repos_id = ?', $repository['repos_id']));
        
        $this->_client->send('Repository removed', $request, DASBiT_Irc_Client::TYPE_NOTICE);
    }
    
    /**
     * Watch for updates
     *
     * @return void
     */
    public function watchUpdates()
    {
        $select = $this->_adapter
                       ->select()
                       ->from('repositories',
                              array('repos_id',
                                    'repos_url',
                                    'repos_channel',
                                    'repos_info_url',
                                    'repos_last_revision'))
                       ->where('repos_last_revision >= 0');
                              
        $repositories = $this->_adapter->fetchAll($select);
        $client       = new Zend_Http_Client();
        
        foreach ($repositories as $repository) { 
            $commits = $this->_getCommits($repository['repos_url'], ($repository['repos_last_revision'] + 1) . ':HEAD');
            
            foreach ($commits as $commit) {
                $response = sprintf('[SVN:r%d:%s] %s',
                                    $commit['revision'],
                                    $commit['username'],
                                    $commit['content']);
                                    
                if (!empty($repository['repos_info_url'])) {
                    $url = str_replace('%r', $commit['revision'], $repository['repos_info_url']);
                    
                    $client->setUri('http://tinyurl.com/api-create.php?url=' . $url);
                    $response = $client->request();
                    
                    if ($response->isSuccessful()) {
                        $url = $response->getBody();   
                    }
                    
                    $response .= sprintf(' (See: %s)', $url);
                }
    
                $this->_client->send($response, $repository['repos_channel']);
                
                $this->_adapter->update('repositories',
                                        array('repos_last_revision' => $commit['revision']),
                                        'repos_id = ' . ($repository['repos_id'])); 
            }
        }
    }
    
    /**
     * Get commits according to the given range
     *
     * @param  string $url
     * @param  string $range
     * @return array
     */
    protected function _getCommits($url, $range)
    {
        $logResults = explode("\n", shell_exec('svn log --non-interactive -r' . $range . ' ' . $url . '  2>&1'));
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