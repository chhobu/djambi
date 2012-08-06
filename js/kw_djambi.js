(function ($) {
  Drupal.behaviors.Djambi = {attach: function(context, settings) {
    var timeouts = new Array();
    var intervals = new Array();
    var clearTimeouts = function() {
      for(var i=0;i < timeouts.length;i++) {
        clearTimeout(timeouts[i]);
      }
      timeouts = [];
    };
    var giveFocus = function($obj) {
      $parent = $obj.parent();
      oldFocus = $parent.css('z-index');
      $parent.css('z-index', 100 + $parent.data('order')).data('focus', oldFocus);
    }
    var removeFocus = function($obj) {
      $obj.parent().css('z-index', $parent.data('focus'));
    }
    var startBlinkingCells = function($cell) {
      i = $cell.data('coords');
      intervals[i] = setInterval(function($obj, i) {
        nbcalls = $obj.data('calls');
        if (nbcalls > 0) {nbcalls++;} else {nbcalls = 1;}
        if (nbcalls > 20) {
          $obj.addClass('recent-change');
          clearInterval(intervals[i]);
        }
        else {
          $obj.toggleClass('recent-change').data('calls', nbcalls);
        }
      }, 500, $cell, i);
    }
    $('.djambigrid .cell.recent-change').not('.blinked').addClass('blinked').each(function() {
      startBlinkingCells($(this));
    });
    $('.djambi .change').not('.processed').addClass('processed').each(function() {
      order = $(this).data('order');
      $change = $(this).find('.description').hide().click(function() {
        clearTimeouts();
        removeFocus($(this));
        $(this).hide('fast');
      });
      timeouts[timeouts.length] = setTimeout(function($obj) {
        $obj.show('slow'); giveFocus($obj);}, (order-1)*2000 + 50, $change);
      timeouts[timeouts.length] = setTimeout(function($obj) {
        $obj.hide('slow'); removeFocus($obj);}, (order-1)*2000 + 5000, $change);
      $(this).find('.order').click(function() {
        clearTimeouts();
        $desc = $(this).siblings('.description');
        if ($desc.is(':visible')) {
          $desc.hide('fast');
          removeFocus($(this));
        } 
        else {
          $desc.show('fast');
          giveFocus($(this));
        }
      });
    });
    $('.djambi .refresh-button').hide();
    $('.djambi', context).once('Djambi', function() {
      var gridId = $(this).data('grid');
      if ($(this).data('refresh') == 'yes') {
        var refreshInterval = setInterval(function() {
          $grid = $('#DjambiContainer' + gridId);
          if ($grid.data('refresh') == 'no') {
            clearInterval(refreshInterval);
          }
          else {
            jQuery.ajax('/djambi/' + gridId + '/check-update/' + $grid.data('version')).done(function(json) {
              result = jQuery.parseJSON(json);
              if(result.changed == 0) {
                $grid.find('.time-elapsed').html(result['time-elapsed']);
                $grid.find('.time-last-update').html(result['time-last-update']);
                if(typeof result['pings'] != 'undefined') {
                  $players_table = $grid.find('.players');
                  for (key in result['pings']) {
                    value = result.pings[key];
                    $tr = $players_table.find('tr[data-djuid="'+ key +'"]');
                    $tr.find('td.ping-info').html(value['status'])
                      .removeClass().addClass('ping-info').addClass(value['class'])
                      .attr('title', value['title']);
                    $tr.find('td.joined').html(value['joined']);
                  }
                }
              }
              else {
                $('.djambi .refresh-button').show().attr('value', Drupal.t('Refreshing...')).mousedown();
              }
            });
          }
        }, 10000);
      }
    });
    $(".djambigrid .cell.std").not(".throne").hover(function() {
      if($(this).parents(".djambigrid tbody").hasClass("dragging")) {$(this).stop().css("background-color","");return;}
      if(!$(this).data("originalBgColor")) {$(this).data("originalBgColor", $(this).css("background-color"));}
      var colors = ["#fea", "#eeb", "#dfb", "#fdb", "#bdf", "#ddf", "#fd6", "#de9", "#fcc", "#fcb"];
      $(this).stop().animate({ backgroundColor: colors[Math.floor(Math.random() * colors.length)]}, 1000);
    },function() {
      if($(this).parents(".djambigrid tbody").hasClass("dragging")) {$(this).stop().css("background-color","");return;}
      $(this).stop().animate({backgroundColor: $(this).data("originalBgColor")}, 1000, function() {
        $(this).css("background-color", "");
        $(this).removeData("originalBgColor");
      });  
    });
    var $pieces = $(".piece.movable");
    if ($pieces.length > 0 && $(".djambigrid .cell.with-selected-piece").length == 0) {
      $pieces.draggable({
        cursor: "move",
        containment: $(".djambigrid tbody"),
        revert: "invalid",
        snap: ".cell",
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
          var $grid = $(this).parents(".djambigrid tbody");
          $grid.addClass("dragging");
          $(this).data("destination", null).parents(".cell").addClass("unselectable with-selected-piece");
          $(this).children("a").addClass("moved");
          for(var i=0;i<allowedMoves.length;i++) {
            $(".djambigrid .cell[data-coord='"+allowedMoves[i]+"']").addClass("selectable").droppable({
              disabled: false,
              drop: function(event,ui) {
                ui.draggable.data("destination", $(this).data("coord"));
              }
            });
          }
        },
        stop: function(event,ui) {
          var $grid = $(this).parents(".djambigrid tbody");
          $(this).parents(".cell").removeClass("unselectable with-selected-piece");
          $grid.removeClass("dragging");
          var $tds = $grid.find(".cell");
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
  }};
})(jQuery);