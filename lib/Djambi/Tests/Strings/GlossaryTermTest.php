<?php
namespace Djambi\Tests\Strings;

use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;
use Djambi\Tests\BaseDjambiTest;

class GlossaryTermTest extends BaseDjambiTest {
  /**
   * @param string $string
   * @param array $args
   * @param string $expected
   * @param string $expected_with_handler
   *
   * @dataProvider provideTerms
   */
  public function testDisplayTerm($string, $args, $expected, $expected_with_handler) {
    $term = new GlossaryTerm($string, $args);
    $string = $term->__toString();
    $this->assertEquals($expected, $string);
  }

  /**
   * @param string $string
   * @param array $args
   * @param string $expected
   * @param string $expected_with_handler
   *
   * @dataProvider provideTerms
   */
  public function testDisplayTermWithHandler($string, $args, $expected, $expected_with_handler) {
    Glossary::getInstance()->setTranslaterHandler(array($this, 'translaterTestHandler'));
    $term = new GlossaryTerm($string, $args);
    $string2 = $term->__toString();
    $this->assertEquals($expected_with_handler, $string2);
  }

  public function provideTerms() {
    return array(
      array(
        Glossary::SIDE_BLUE,
        NULL,
        Glossary::SIDE_BLUE,
        $this->translaterTestHandler(Glossary::SIDE_BLUE, NULL),
      ),
      array(
        'Test n°!num',
        array('!num' => 2),
        'Test n°2',
        'Tast n°2',
      ),
      array(
        'Test n°!num is %color',
        array('!num' => 3, '%color' => new GlossaryTerm(Glossary::SIDE_BLUE)),
        'Test n°3 is ' . Glossary::SIDE_BLUE,
        'Tast n°3 is ' . $this->translaterTestHandler(Glossary::SIDE_BLUE, NULL),
      ),
    );
  }

  public function translaterTestHandler($string, $args) {
    return strtr(str_replace("e", "a", $string), !is_array($args) ? array() : $args);
  }

  public function testListGlossaryTerms() {
    $terms = Glossary::getInstance()->getGlossaryTerms();
    $this->assertArrayHasKey('PIECE_ASSASSIN', $terms);
    $this->assertEquals($terms['PIECE_ASSASSIN'], Glossary::PIECE_ASSASSIN);
  }

  /**
   * @dataProvider provideTermsForPersistance
   */
  public function testTermPersistance($object, $expected, $context = array()) {
    $this->checkObjectTransformation($object, $expected, $context);
  }

  /**
   * @dataProvider provideTermsForPersistance
   */
  public function testTermSerialization($object, $expected, $context = array()) {
    $this->checkObjectSerialization($object, $expected, $context);
  }

  public function provideTermsForPersistance() {
    $term1 = new GlossaryTerm(Glossary::SIDE_BLUE);
    $values[] = array($term1, array(
      'string' => Glossary::SIDE_BLUE,
      ));
    $term2 = new GlossaryTerm("Test n°!num %check is ?color", array(
      '!num' => 2,
      '%check' => 'must pass !',
      '?color' => $term1,
    ));
    $values[] = array($term2, array(
      'string' => self::CHECK_SAME_VALUE,
      'args' => self::CHECK_SAME_VALUE,
      ));
    return $values;
  }
}
