(function ($, window, Drupal, drupalSettings) {
  Drupal.behaviors.djambiWatching = {attach: function() {
    var $form = $('.djambi-grid-form');
    var intervals = [];
    $form.find('.djambi-infos__last-moves .submoves em.placeholder').not('.is-pointable').addClass('is-pointable').click(function() {
      var text = $(this).text();
      $form.find('.djambi-cell').removeClass('is-blinking');
      clearInterval(intervals['last-moves']);
      highlightCell('last-moves', $form.find('.djambi-cell[data-cell-name="' + text + '"]'));
    });
    $form.find('.djambi-infos__last-moves .djambi-piece-log.is-still-alive').not('.is-pointable').addClass('is-pointable').click(function() {
      var text = $(this).text();
      $form.find('.djambi-cell').removeClass('is-blinking');
      clearInterval(intervals['last-moves']);
      highlightCell('last-moves', $form.find('.djambi-piece:contains("'+ text +'")').parents('.djambi-cell'));
    });
    var highlightCell = function(type, $cell) {
      $cell.data('blinks', 0);
      intervals[type] = setInterval(function($obj, type) {
        var nbcalls = $obj.data('blinks');
        if (nbcalls > 0) {nbcalls++;} else {nbcalls = 1;}
        if (nbcalls > 20) {
          $obj.removeClass('is-blinking');
          clearInterval(intervals[type]);
        }
        else {
          $obj.toggleClass('is-blinking').data('blinks', nbcalls);
        }
      }, 500, $cell);
    };
  }};
}(jQuery, this, Drupal, drupalSettings));