<?php
use Drupal\Core\Template\Attribute;

/**
 * Returns HTML for a description list.
 * "The dl element represents an association list consisting of zero or more
 * name-value groups (a description list). Each group must consist of one or
 * more names (dt elements) followed by one or more values (dd elements). Within
 * a single dl element, there should not be more than one dt element for each
 * name."
 * This means:
 * - The dl element may be empty.
 * - If there is a dt element, it must be followed by a dd element.
 * - There can be multiple dt elements followed by one or more dd element.
 * - Each set of dt elements and dd elements forms a "group".
 * - The text of one dt element must be unique within the dl element.
 * - The dl element must contain dt and dd elements only.
 * @see http://html5doctor.com/the-dl-element/
 * @see http://www.w3.org/TR/html-markup/dl.html
 * @see http://www.w3.org/wiki/HTML_lists#Description_lists
 *
 * @param array $variables
 *   An associative array containing:
 *   - groups: The list of groups to render. Each group is an array keyed by tag
 *     name containing one of the following values:
 *     - A simple string.
 *     - An array of strings.
 *     - A render array.
 *     - An array of render arrays.
 *     Each element will be wrapped in either a dt or dd element depending on
 *     the key of the group array. When using render arrays it is possible to
 *     use the #wrapper_attributes property to specify attributes for the dt/dd
 *     element itself.
 *   - attributes: Optional attributes to apply to the dl element.
 *
 * @return string
 *   The rendered description list.
 */
function template_preprocess_description_list(&$variables) {
  if (!isset($variables['attributes']['class'])) {
    $variables['attributes']['class'] = array();
  }
  $variables['attributes'] = new Attribute($variables['attributes']);
  // A group is a set of zero or more dt and dd name-value pairs.
  if (!empty($variables['groups'])) {
    foreach ($variables['groups'] as &$group) {
      $group_attributes = array();
      if (!empty($group['attributes'])) {
        $group_attributes = $group['attributes'];
      }
      if (isset($group['term'])) {
        $group['terms'] = array($group['term']);
        unset($group['term']);
      }
      if (isset($group['description'])) {
        $group['descriptions'] = array($group['description']);
        unset($group['description']);
      }
      foreach (array('terms', 'descriptions') as $type) {
        if (!isset($group[$type]) || !is_array($group[$type])) {
          continue;
        }
        $nb_items = count($group[$type]);
        $i = 0;
        foreach ($group[$type] as $key => $item) {
          $item_attributes = !empty($item['#wrapper_attributes']) ? $item['#wrapper_attributes'] : array();
          if ($i == 0 && $i + 1 == $nb_items) {
            $item_attributes['class'][] = $type == 'terms' ? 'single-term-item' : 'single-description-item';
          }
          elseif ($i == 0) {
            $item_attributes['class'][] = $type == 'terms' ? 'first-term-item' : 'first-description-item';
          }
          elseif ($i + 1 == $nb_items) {
            $item_attributes['class'][] = $type == 'terms' ? 'last-description-item' : 'last-description-item';
          }
          $group[$type][$i++] = array(
            'value' => $item,
            'attributes' => new Attribute(array_merge_recursive($group_attributes, $item_attributes)),
            'definition' => !empty($item['#definition']),
          );
        }
      }
    }
  }
}
