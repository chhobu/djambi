Feature: Game creation
  In order to play a new Djambi game
  As an anonymous Djambi player
  I need to create a Djambi grid with pieces

  Scenario: Create a 4 sides standard game
    Given I am a Djambi Player
    When I initiate a new 4 players game in a standard grid
    Then I should have a 81 squares chessboard
    And I should have 4 sides
    And I should have 9 pieces per side
    And I should have the following pieces positions:
      | piece type  | Red | Blue | Yellow | Green |
      | Leader      | A9  | I9   | I1     | A1    |
      | Assassin    | B9  | I8   | H1     | A2    |
      | Reporter    | A8  | H9   | I2     | B1    |
      | Diplomat    | B8  | H8   | H2     | B2    |
      | Militant    | B7  | H7   | H3     | B3    |
      | Militant    | C9  | G9   | G1     | C1    |
      | Necromobile | C7  | G7   | G3     | C3    |
      | Militant    | C8  | G8   | G2     | C2    |
      | Militant    | A7  | I7   | I3     | A3    |

  Scenario: Create a 3 Sides hexagonal game
    Given I am a Djambi Player
    When I initiate a new 3 players game in an hexagonal grid
    Then I should have a 61 squares chessboard
    And I should have 3 sides
    And I should have 9 pieces per side
    And I should have the following pieces positions:
      | piece type  | Red | Blue | Yellow |
      | Leader      | A5  | G9   | G1     |
      | Assassin    | A6  | G8   | F1     |
      | Reporter    | A4  | F9   | G2     |
      | Diplomat    | B5  | F8   | F2     |
      | Militant    | B3  | G7   | E1     |
      | Militant    | B4  | H7   | E2     |
      | Necromobile | C5  | F7   | F3     |
      | Militant    | B6  | E8   | G3     |
      | Militant    | B7  | E9   | H3     |

  Scenario: Create a 2 Sides standard game
    Given I am a Djambi Player
    When I initiate a new 2 players game in a standard grid
    Then I should have a 81 squares chessboard
    And I should have 4 sides
    And I should have 9 pieces per side
    And Side "Blue" should be vassalized and controlled by "Red" side
    And Side "Green" should be vassalized and controlled by "Yellow" side

  Scenario: Create a 2 Sides mini game
    Given I am a Djambi Player
    When I initiate a new 2 players game in a mini grid
    Then I should have a 49 squares chessboard
    And I should have 2 sides
    And I should have 4 pieces per side
    And I should have the following pieces positions:
      | piece type                       | Red | Blue |
      | Leader                           | A7  | G1   |
      | Diplomat or Reporter or Assassin | B6  | F2   |
      | Militant                         | B7  | F1   |
      | Militant                         | A6  | G2   |