<?php
class DjambiPieceScheme {
  private $piece_scheme;

  public function __construct($type = 'standard') {
    switch($type) {
      default:
        $this->createStandardScheme();
    }
  }

  public function getPieceScheme() {
    return $this->piece_scheme;
  }

  private function createStandardScheme() {
    $militant_habilities = array('limited_move' => 2, 'kill_by_attack' => TRUE);
    $this->piece_scheme = array(
        'L' => array(
            'shortname' => 'Lea',
            'longname' => 'Leader',
            'type' => 'leader',
            'habilities' => array('kill_throne_leader' => TRUE, 'access_throne' => TRUE, 'kill_by_attack' => TRUE, 'must_live' => TRUE)
        ),
        'R' => array(
            'shortname' => 'Rep',
            'longname' => 'Reporter',
            'type' => 'reporter',
            'habilities' => array('kill_by_proximity' => TRUE, 'kill_throne_leader' => TRUE)
        ),
        'M1' => array(
            'shortname' => 'M#1',
            'longname' => 'Militant #1',
            'type' => 'militant',
            'habilities' => $militant_habilities
        ),
        'M2' => array(
            'shortname' => 'M#2',
            'longname' => 'Militant #2',
            'type' => 'militant',
            'habilities' => $militant_habilities
        ),
        'M3' => array(
            'shortname' => 'M#3',
            'longname' => 'Militant #3',
            'type' => 'militant',
            'habilities' => $militant_habilities
        ),
        'M4' => array(
            'shortname' => 'M#4',
            'longname' => 'Militant #4',
            'type' => 'militant',
            'habilities' => $militant_habilities
        ),
        'A' => array(
            'shortname' => 'Sni',
            'longname' => 'Sniper',
            'type' => 'assassin',
            'habilities' => array('kill_by_attack' => TRUE, 'kill_signature' => TRUE, 'kill_throne_leader' => TRUE)
        ),
        'D' => array(
            'shortname' => 'Dip',
            'longname' => 'Diplomat',
            'type' => 'diplomate',
            'habilities' => array('move_living_pieces' => TRUE)
        ),
        'N' => array(
            'shortname' => 'Nec',
            'longname' => 'Necromobil',
            'type' => 'necromobile',
            'habilities' => array('move_dead_pieces' => TRUE)
        )
    );
  }
}