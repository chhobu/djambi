Feature: Militant actions
  In order to play a Djambi game according to the standard rules
  As a Djambi player
  I need to respect the rules about the Militant piece

  Background:
    Given a custom 49 squares chessboard with the following pieces:
      | piece type  | side    | position  | status |
      | Leader      | Red     | A7        | alive  |
      | Militant    | Red     | B6        | alive  |
      | Militant    | Red     | E3        | alive  |
      | Militant    | Blue    | F2        | alive  |
      | Militant    | Blue    | C6        | dead   |
      | Leader      | Blue    | D3        | alive  |

  Scenario Outline: Testing Militant possible moves
    Given I am a Djambi player controlling the "Red" Side
    When I select the "Militant" in "<start position>"
    Then I should be able to move it to "<possible end positions>"
    And I should get an error when trying to move it not to "<possible end positions>"

    Examples:
      | start position | possible end positions                 |
      | B6             | A6 B7 B5 B4 A5 C5 C7                   |
      | E3             | E1 E2 E4 E5 D2 D3 C1 F3 G3 C5 F2 F4 G5 |

  Scenario: A Militant cannot kill a Leader in throne
    Given I am a Djambi player controlling the "Red" Side
    And Piece "Leader" from faction "Blue" has been moved to throne
    When I select the "Militant" in "B6"
    Then I should get an error when trying to move it to "D4"

  Scenario: A Militant can kill a piece and place it on a free cell
    Given I am a Djambi player controlling the "Red" Side
    When I select the "Militant" in "E3"
    And I move it to "F2"
    Then the piece "Militant" from faction "Blue" should be selected
    And I should get an error when trying to bury it in "C6, A7, B6, F2, C6, D4 or D3"
    And I should be able to place it in "A1"
    Then the piece "Militant" from faction "Blue" should be dead
    And my turn shall end

  Scenario: A Militant can kill a leader outside the throne, leading to victory
    Given I am a Djambi player controlling the "Red" Side
    When I select the "Militant" in "E3"
    And I move it to "D3"
    Then the piece "Leader" from faction "Blue" should be selected
    And I should get an error when trying to bury it in "C6, A7, B6, F2, C6, D4 or D3"
    And I should be able to place it in "A1"
    Then the piece "Leader" from faction "Blue" should be dead
    And I shall have won the game