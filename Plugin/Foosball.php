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
 * Plugin to handle foosball scores
 */
class Plugin_Foosball extends DASBiT_Plugin
{
    /**
     * Database adapter
     *
     * @var Zend_Db_Adapter_Pdo_Sqlite
     */
    protected $_adapter;

    /**
     * Default score for new users.
     */
    protected $_defaultScore = 1000;

    /**
     * The kfactor for calculating EOL rating.
     */
    protected $_kfactor = 16;

    /**
     * Defined by DASBiT_Plugin
     *
     * @return void
     */
    protected function _init()
    {
        $this->_adapter = DASBiT_Database::accessDatabase('foosball', array(
            'foosball_scores' => array(
                'id'          => 'INTEGER PRIMARY KEY',
                'name'        => 'VARCHAR(128)',
                'score'       => 'INTEGER',
            ),
            'foosball_matches' => array(
                'id'     => 'INTEGER PRIMARY KEY',
                'winner' => 'VARCHAR(128)',
                'loser'  => 'VARCHAR(128)',
                'date'   => 'INTEGER',
            ),
        ));

        $this->_controller->registerCommand($this, 'parseMatch', 'foos');
        $this->_controller->registerTrigger($this, 'parseMatch', '#^(?!.*foos )#');
    }

    /**
     * Parse wins/loses for a match.
     *
     * @param  DASBiT_Irc_Request $request
     * @return void
     */
    public function parseMatch(DASBiT_Irc_Request $request)
    {
        $commandData = $request->getWords();
        $command     = $commandData[1];

        if ($command == 'scores') {
          return $this->getScores($request);
        }

        if (preg_match('#^.*?foos (.*?) beat (.*)$#', $request->getMessage(), $matches) === 0) {
            $this->_client->send('Wrong syntax, use "foos [username]  => description"', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }

        $winner = $matches[1];
        $loser = $matches[2];
        $scores = $this->calculateScores($winner, $loser);

        if (isset($scores)) {
          $scores_output = array();

          foreach ($scores as $user => $score) {
            $this->saveScore($user, $score);
            $scores_output[] = $user . ': ' . $score;
          }

          $this->_client->send('New scores: ' . join(' | ', $scores_output), $request);
        }
    }

    /**
     * Gets scores for all users.
     */
    public function getScores(DASBiT_Irc_Request $request)
    {
        $select = $this->_adapter
                       ->select()
                       ->from('foosball_scores',
                              array('name', 'score'))
                       ->order(array('score DESC'));

        $rows = $this->_adapter->fetchAll($select);

        $scores = array();
        foreach ($rows as $row) {
          $scores[] = $row['name'] . ': ' . $row['score'];
        }

        if ($scores) {
          $this->_client->send(join(' | ', $scores), $request);
        }
    }

    /**
     * Calculate new scores.
     */
    protected function calculateScores($winner, $loser)
    {
      $winner_rating = $this->getScore($winner);
      $loser_rating = $this->getScore($loser);

      $winner_expected = 1 / ( 1 + ( pow( 10 , ( $loser_rating - $winner_rating ) / 400 ) ) );
      $loser_expected = 1 / ( 1 + ( pow( 10 , ( $winner_rating - $loser_rating ) / 400 ) ) );

      $winner_score = $winner_rating + ( $this->_kfactor * ( 1 - $winner_expected ) );
      $loser_score = $loser_rating + ( $this->_kfactor * ( 0 - $loser_expected ) );

      $this->logMatch($winner, $loser);

      return array (
        $winner => round($winner_score),
        $loser => round($loser_score),
      );
    }

    /**
     * Logs a match between two users.
     */
    protected function logMatch($winner, $loser) {
        $this->_adapter->insert('foosball_matches', array(
            'winner' => $winner,
            'loser'  => $loser,
            'date'   => time(),
        ));
    }

    /**
     * Gets a score for a user.
     */
    protected function getScore($user)
    {
        $select = $this->_adapter
                       ->select()
                       ->from('foosball_scores',
                              array('score'))
                       ->where('name = ?', $user);

        $score = $this->_adapter->fetchRow($select);

        return $score ? $score['score'] : $this->_defaultScore;
    }

    /**
     * Saves a score for a user.
     */
    protected function saveScore($user, $score)
    {
        $select = $this->_adapter
                       ->select()
                       ->from('foosball_scores',
                              array('id'))
                       ->where('name = ?', $user);

        $foosball = $this->_adapter->fetchRow($select);

        if ($foosball === false) {
            $this->_adapter->insert('foosball_scores', array(
                'name' => $user,
                'score'   => $score
            ));
        } else {
            $this->_adapter->update('foosball_scores', array(
                'score' => $score
            ), 'id = ' . $foosball['id']);
        }
    }
}
