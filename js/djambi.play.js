(function ($, Drupal) {
  Drupal.behaviors.djambiPlaying = {attach: function() {
    var $form = $('.djambi-grid-form');
    var $grid = $('.djambi-grid');
    // Glisser-déposer d'une pièce
    var $pieces = $grid.find('label.djambi-piece');
    if ($pieces.length > 0) {
      $pieces.draggable({
        containment: $grid.is("table") ? $grid.children("tbody") : $grid,
        revert: "invalid",
        snap: ".djambi-cell",
        snapTolerance: 5,
        start: function () {
          var allowedMoves = $(this).data("reachable-cells").split(" ");
          var $container = $(this).parents(".djambi-grid");
          $(this).addClass("is-dragged").parent().addClass("is-selected").removeClass("is-selectable");
          $container.find(".is-selectable").removeClass("is-selectable").addClass("is-with-movable-piece");
          $container.addClass("is-dragging").addClass("is-with-selected-piece");
          for (var i = 0; i < allowedMoves.length; i++) {
            $container.find(".djambi-cell[data-cell-name='" + allowedMoves[i] + "']")
              .addClass("is-selectable").addClass("is-droppable").droppable({
                disabled: false,
                drop: function (event, ui) {
                  ui.draggable.data("destination", $(this).data("cell-name"));
                  $(this).addClass('is-highlighted');
                  $(this).parents("form").find("input[name=\"js-extra-choice\"]").val($(this).data("cell-name"));
                },
                over: function () {
                  $(this).addClass('is-highlighted');
                },
                out: function () {
                  $(this).removeClass('is-highlighted');
                }
              });
          }
        },
        stop: function () {
          var $container = $(this).parents(".djambi-grid");
          $container.find(".djambi-cell").filter(".is-selectable.is-droppable").removeClass("is-droppable").removeClass("is-selectable").droppable("destroy");
          if ($(this).data("destination") != null) {
            $pieces.draggable("destroy");
          }
          else {
            $(this).removeClass("is-dragged").parent().removeClass("is-selected").addClass("is-selectable");
            $container.find(".is-with-movable-piece").removeClass("is-with-movable-piece").addClass("is-selectable");
            $container.removeClass("is-dragging").removeClass("is-with-selected-piece");
          }
        }
      });
    }
    // Soumission automatique du formulaire lors de la sélection d'une pièce
    $grid.find('label').click(function() {
      var $form = $(this).parents('form');
      setTimeout(function() {$form.find('input.button--primary').mousedown();}, 100);
    });
    // Annulation de la sélection d'une pièce au profit d'une autre
    $grid.find('.is-with-movable-piece').not('label').addClass('is-pointable').click(function() {
      var $form = $(this).parents('form');
      $form.find("input[name=\"js-extra-choice\"]").val($(this).data('cell-name'));
      $form.find('input.button--cancel-selection').mousedown();
    });
    // Application d'un style sur le conteneur du bouton radio sélectionné
    $form.find('.form-radios input.form-radio').change(function() {
      $(this).parents('.form-radios').find('.form-item').removeClass('is-selected');
      if ($(this).is(':checked')) {
        $(this).parents('.form-item').addClass('is-selected');
      }
      if ($(this).attr('name') == 'cells') {
        $(this).parents('form').find('.djambi-cell').removeClass('is-highlighted')
          .filter('[data-cell-name=\"' + $(this).val() + '\"]').addClass('is-highlighted');
      }
    });
    // Positionnement d'une pièce suite à une interaction
    var $positionable = $grid.find(".djambi-piece.is-positionnable");
    if ($positionable.length == 1) {
      var orig_offset = $positionable.offset();
      $grid.mouseenter(function() {
        $(this).bind('mousemove', function(e){
          $positionable.offset({
            left:  e.pageX,
            top:   e.pageY
          });
        });
      }).mouseleave(function() {
        $(this).unbind('mousemove');
        $positionable.offset(orig_offset);
      });
    }
  }};
}(jQuery, Drupal));