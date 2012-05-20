Drupal.behaviors.Djambi = {attach: function(context) {(function ($) {
  $(".djambigrid td.std").not(".throne").hover(function() {
      if($(this).parents("table.djambigrid tbody").hasClass("dragging")) {$(this).stop().css("background-color","");return;}
      if(!$(this).data("originalBgColor")) {$(this).data("originalBgColor", $(this).css("background-color"));}
      var colors = ["#fea", "#eeb", "#dfb", "#fdb", "#bdf", "#ddf", "#fd6", "#de9", "#fcc", "#fcb"];
      $(this).stop().animate({ backgroundColor: colors[Math.floor(Math.random() * colors.length)]}, 1000);
    },function() {
      if($(this).parents("table.djambigrid tbody").hasClass("dragging")) {$(this).stop().css("background-color","");return;}
      $(this).stop().animate({backgroundColor: $(this).data("originalBgColor")}, 1000, function() {
        $(this).css("background-color", "");
        $(this).removeData("originalBgColor");
      });  
    }
  );
  var $pieces = $(".piece.movable");
  if ($("table.djambigrid td.with-selected-piece").length == 0) {
    $pieces.draggable({
      cursor: "move",
      containment: $("table.djambigrid tbody"),
      revert: "invalid",
      snap: "td",
      snapMode: "inner",
      create: function(event,ui) {
        $(this).children("input").each(function() {
          $(this).hide().after("<a href='#'><img src='"+$(this).attr("src")+"' alt=\""+$(this).attr("alt")+"\" /></a>");
        });
        $(this).children("a").click(function() {
          if(!$(this).hasClass("moved") && $(this).parents(".piece.movable").data("destination") == null) {
            $pieces.draggable("destroy").children("a").addClass("moved");
            $(this).siblings("input").show().mousedown();
          }
          return false;
        });
      },
      start: function(event,ui) {
        var allowedMoves = $(this).data("moves").split(" ");
        var $grid = $(this).parents("table.djambigrid tbody");
        $grid.addClass("dragging");
        $(this).data("destination", null).parents("td").addClass("unselectable with-selected-piece");
        $(this).children("a").addClass("moved");
        for(var i=0;i<allowedMoves.length;i++) {
          $("table.djambigrid td[data-coord='"+allowedMoves[i]+"']").addClass("selectable").droppable({
            disabled: false,
            drop: function(event,ui) {
              ui.draggable.data("destination", $(this).data("coord"));
            }
          });
        }
      },
      stop: function(event,ui) {
        var $grid = $(this).parents("table.djambigrid tbody");
        $(this).parents("td").removeClass("unselectable with-selected-piece");
        $grid.removeClass("dragging");
        var $tds = $grid.find("td");
        $tds.each(function() {$(this).removeClass("selectable").droppable("destroy");});
        if($(this).data("destination") != null) {
          $(this).parents("form").find("input[name='piece_destination']").val($(this).data("destination"));
          $pieces.draggable("destroy").children("a").addClass("moved");
          $tds.removeClass("reachable with-movable-piece");
          $(this).children("input").show().mousedown();
        }
        else {
          $(this).children("a").removeClass("moved");
        }
      }
    });
  }
})(jQuery);}};