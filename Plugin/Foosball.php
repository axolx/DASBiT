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
                'date'   => 'INTEGER',
            ),
            'foosball_matches' => array(
                'id'     => 'INTEGER PRIMARY KEY',
                'winner' => 'VARCHAR(128)',
                'loser'  => 'VARCHAR(128)',
                'date'   => 'INTEGER',
            ),
            'foosball_players' => array(
              'id'     => 'INTEGER PRIMARY KEY',
              'name' => 'VARCHAR(128)',
            ),
        ));

        $this->_controller->registerCommand($this, 'parseMatch', 'foos');
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
          if ($commandData[2] == 'doubles'){
            return $this->getDoublesScores($request);
          } else {
            return $this->getScores($request);
          }
        }

        if ($command == 'addplayer'){
          $this->savePlayer($commandData[2]);
          $this->_client->send($commandData[2] . ' has been assimilated.', $request);
        }

        if ($command == 'replay'){
          //Commented out for now. Accidents would kill bubot.
          //$this->replayMatches($request);
        }


        if (preg_match('#^.*?foos (.*?) beat (.*)$#', $request->getMessage(), $matches) === 0) {
            $this->_client->send('Wrong syntax, use "foos [username]  => description"', $request, DASBiT_Irc_Client::TYPE_NOTICE);
            return;
        }

        if (count($commandData) == 4 ) {
          $players['winner'] = $commandData[1];
          $players['loser'] = $commandData[3];
        }
        else if  (count($commandData) == 6 ){
          //Doubles Score
          $players['winner'] = $commandData[1];
          $players['winner2'] = $commandData[2];

          $players['loser']  = $commandData[4];
          $players['loser2']  = $commandData[5];
        }

        $all_players = $this->getPlayers();

        //Check to make sure that all player names entered are valid players
        $playercount = count($players);
        $values = array_values($players);
        $shizzle = array_intersect($all_players, array_values($players));

        if (count(array_intersect($all_players, array_values($players))) !== count($players)) {
          $this->_client->send('Unknown Player, use "!foos addPlayer [playername] to add the player to the system"', $request);
          return;
        }
        if (count($commandData) == 4 ) {
          $scores = $this->calculateScores($players['winner'], $players['loser']);
          $this->logMatch($players['winner'], $players['loser']);
        }
        else if  (count($commandData) == 6 ){
          $scores = $this->calculateDoublesScores($players['winner'], $players['winner2'], $players['loser'], $players['loser2']);
          $this->logMatch($players['winner'] . '+' . $players['winner2'], $players['loser'] . '+' .$players['loser2']);
        }

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
        $doubles = '%doubles';
        $select = $this->_adapter
                       ->select()
                       ->from('foosball_scores',
                              array('name', 'score', 'max(date)'))
                       ->group('name')
                       ->where('name NOT LIKE ?', $doubles)
                       ->order(array('score DESC'));

        $rows = $this->_adapter->fetchAll($select);

        $namekey = array_values(preg_grep('/name/', array_keys($rows[0])));
        $scorekey = array_values(preg_grep('/score/', array_keys($rows[0])));

        foreach ($rows as $row) {
          $scores[] = $row[$namekey[0]] . ': ' . $row[$scorekey[0]];
        }

      if ($scores) {
        $this->_client->send(join(' | ', $scores), $request);
      }
    }

    public function getDoublesScores(DASBiT_Irc_Request $request)
    {

      $doubles = '%doubles';
      $select = $this->_adapter
        ->select()
        ->from('foosball_scores',
        array('name', 'score', 'max(date)'))
        ->group('name')
        ->where('name LIKE ?', $doubles)
        ->order(array('score DESC'));

      $rows = $this->_adapter->fetchAll($select);

      $namekey = array_values(preg_grep('/name/', array_keys($rows[0])));
      $scorekey = array_values(preg_grep('/score/', array_keys($rows[0])));

      foreach ($rows as $row) {
        $scores[] = $row[$namekey[0]] . ': ' . $row[$scorekey[0]];
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



      return array (
        $winner => round($winner_score),
        $loser => round($loser_score),
      );
    }
    /**
     * Calculate new scores.
     */
    protected function calculateDoublesScores($winner,$winner2, $loser, $loser2)
    {
      $winner1_rating = $this->getScore($winner . ":doubles");
      $winner2_rating = $this->getScore($winner2 . ":doubles");
      $loser1_rating = $this->getScore($loser . ":doubles");
      $loser2_rating = $this->getScore($loser2 . ":doubles");

      $winner_rating = ( $winner1_rating + $winner2_rating ) / 2;
      $loser_rating = ( $loser1_rating + $loser2_rating ) / 2;

      $winner_expected = 1 / ( 1 + ( pow( 10 , ( $loser_rating - $winner_rating ) / 400 ) ) );
      $loser_expected = 1 / ( 1 + ( pow( 10 , ( $winner_rating - $loser_rating ) / 400 ) ) );

      $winner_delta = $this->_kfactor * ( 1 - $winner_expected );
      $loser_delta = $this->_kfactor * ( 0 - $loser_expected );


      return array (
        $winner . ":doubles" => round($winner1_rating + $winner_delta),
        $winner2 . ":doubles" => round($winner2_rating + $winner_delta),
        $loser . ":doubles" => round($loser1_rating + $loser_delta),
        $loser2 . ":doubles" => round($loser2_rating + $loser_delta),
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
                       ->where('name = ?', $user)
                       ->order(array('date DESC'));

        $score = $this->_adapter->fetchRow($select);

        return $score ? $score['score'] : $this->_defaultScore;
    }

    /**
     * Saves a score for a player.
     */
    protected function saveScore($user, $score, $date = NULL)
    {
      if ($date == NULL){
        $date = time();
      }
      $this->_adapter->insert('foosball_scores', array(
          'name'    => $user,
          'score'   => $score,
          'date'    => $date,
      ));

    }
    /**
     * Add a player.
     */
    protected function savePlayer($user)
    {
      $select = $this->_adapter
        ->select()
        ->from('foosball_players',
        array('id'))
        ->where('name = ?', $user);

      $foosball = $this->_adapter->fetchRow($select);

      if ($foosball === false) {
        $this->_adapter->insert('foosball_players', array(
          'name' => $user
        ));
      } else {
        // this is just a silly stub in case we add more data to the players
        $this->_adapter->update('foosball_players', array(
          'name' => $user
        ), 'id = ' . $foosball['id']);
      }

    }
    protected function getPlayers()
    {
      $players = array();
      $select = $this->_adapter
        ->select()
        ->from('foosball_players',
        array('name'));


      $rows = $this->_adapter->fetchAll($select);
      foreach ($rows as $player){
        $players[] = $player['name'];
      }

     return $players;
    }

  //TODO add support to replay doubles matches
  protected function replayMatches(DASBiT_Irc_Request $request)
  {
    $select = $this->_adapter
      ->select()
      ->from('foosball_matches',
      array('winner','loser','date'));


    $rows = $this->_adapter->fetchAll($select);

    foreach ($rows as $match){
      $scores = $this->calculateScores($match['winner'], $match['loser']);
      if (isset($scores)) {
        $scores_output = array();

        foreach ($scores as $user => $score) {
          $this->saveScore($user, $score, $match['date']);
          $scores_output[] = $user . ': ' . $score;
        }

        $this->_client->send('New scores: ' . join(' | ', $scores_output), $request);
      }
    }

    return '';
  }
}
