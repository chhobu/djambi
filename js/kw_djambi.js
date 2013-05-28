(function ($) {
  $.fn.scrollTo = function() {
    $('body,html').animate({
      'scrollTop' : $(this).offset().top
    }, 'slow');
  };
  Drupal.behaviors.Djambi = {attach: function(context, settings) {
    // Rafraichissement du tableau récapitulatif des camps en jeu
    $('.refresh-my-djambi-panel').once('DjambiPanel', function() {
      $(this).hide();
      if (typeof panelInterval !== 'undefined') {
        clearInterval(panelInterval);
      }
      panelInterval = setInterval(function() {
        $link = $('.refresh-my-djambi-panel a')
        if ($link.length > 0) {
          $link.click();
        }
        else {
          clearInterval(panelInterval);
        }
      }, 60000);
    });
    // Scripts passés sur une partie de type Djambi
    $('.djambi').once('Djambi', function() {
      var $djambi = $(this);
      var gridId = $djambi.data('grid');
      var autoplay = false;
      if ($(this).hasClass('autoplay')) {
        autoplay = true;
      }
      if (autoplay) {
        $djambi.find('input[name="ui-replay-pause"]').show();
      }
      else {
        $djambi.find('input[name="ui-replay-autoplay"]').show();
      }
      var $djambigrid = $djambi.find('.djambigrid');
      var timeouts = {'desc': [], 'move': []};
      var intervals = new Array();
      var clearTimeouts = function(type) {
        for(var i=0;i < timeouts[type].length;i++) {
          clearTimeout(timeouts[type][i]);
        }
        timeouts[type] = [];
        return true;
      };
      var giveFocus = function($obj) {
        $parent = $obj.parent();
        oldFocus = $parent.css('z-index');
        $parent.css('z-index', 100 + $parent.data('order')).data('focus', oldFocus);
      };
      var removeFocus = function($obj) {
        $obj.parent().css('z-index', $parent.data('focus'));
      };
      // Affichage des derniers mouvements : clignotement des cases concernées
      var startBlinkingCells = function($cell) {
        i = $cell.data('coord');
        intervals[i] = setInterval(function($obj, i) {
          nbcalls = $obj.data('calls');
          if (nbcalls > 0) {nbcalls++;} else {nbcalls = 1;}
          if (nbcalls > 20) {
            $obj.addClass('past-move');
            clearInterval(intervals[i]);
          }
          else {
            $obj.toggleClass('past-move').data('calls', nbcalls);
          }
        }, 500, $cell, i);
      };
      var launchAnimations = function(clearable) {
        ends = 0;
        var animate = false;
        $djambigrid.find('.cell.past-move').not('.blinked').addClass('blinked').each(function() {
          startBlinkingCells($(this));
          animate = true;
        });
        // Affichage des derniers changements : descriptions
        $djambigrid.find('.change').each(function() {
          order = $(this).data('order');
          $change = $(this).find('.description').hide().click(function() {
            if (clearable) {clearTimeouts('desc');}
            removeFocus($(this));
            $(this).hide('fast');
          });
          if (animate && $change.length > 0) {
            timeouts['desc'][timeouts['desc'].length] = setTimeout(function($obj) {
              $obj.show('slow'); giveFocus($obj);}, (order+1) * 2000 + 50, $change);
            ends = (order+1) * 2000 + 5000;
            timeouts['desc'][timeouts['desc'].length] = setTimeout(function($obj) {
              $obj.hide('slow'); removeFocus($obj);}, ends, $change);
            ends = ends + 500;
          }
          $(this).find('.order').click(function() {
            if (clearable) {clearTimeouts('desc');}
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
        // Récapitulatif des parties : déplacement des pièces
        if ($djambigrid.hasClass('animated')) {
          i = 0;
          $piece = $djambigrid.find('.piece[data-animation-'+i+']');
          if($piece.length > 0) {
            $djambigrid.find('.changes .order').hide();
            while($piece.length > 0) {
              $djambigrid.data('animation-order', 0);
              timeouts['move'][timeouts['move'].length] = setTimeout(function($obj) {
                $grid = $obj.parents('.djambigrid');
                order = $grid.data('animation-order');
                if (typeof $obj.data('animation-'+ order) === 'undefined') {
                  clearTimeouts('move');
                  clearTimeouts('desc');
                  return false;
                }
                moveData = $obj.data('animation-'+ order).split(':');
                $grid.data('animation-order', order + 1);
                moveType = moveData[0];
                if (moveType == 'murder') {
                  $obj.removeClass('alive').addClass('dead');
                }
                $moveDestination = $obj.parents('.djambigrid').find('.cell[data-coord="'+ moveData[1] +'"]');
                $moveDestination.find('.changes .order').show('slow');
                if (moveData[1] != $obj.parent().data('coord')) {
                  currentPositions = $obj.offset();
                  $newPiece = $obj.clone();
                  $moveDestination.prepend($newPiece);
                  newPositions = $newPiece.offset();
                  $newPiece.remove();
                  marginTop = parseInt($obj.parent().css('margin-top'));
                  $obj.css({'position':'relative', 'z-index':'1'}).animate({
                    top: newPositions.top - currentPositions.top + marginTop, 
                    left: newPositions.left - currentPositions.left
                  }, {duration: 2000});
                }
              }, i * 2000 + 500, $piece);
              i++;
              $piece = $djambigrid.find('.piece[data-animation-'+i+']');
            }
            if (ends == 0) {ends = i * 2000 + 500;}
          }
          if (autoplay) {
            autoReplayTimeout = setTimeout(function() {
              $next = $('input[name="ui-replay-forward"]');
              $parent = $next.parents('.djambi');
              clearTimeout(autoReplayTimeout);
              if ($parent.hasClass('autoplay')) {
                if ($next.is(':enabled')) {$next.mousedown();}
                else {$('input[name="ui-replay-end"]').mousedown();}
              }
            }, Math.max(ends + 500, 2000));
          }
        }
        return ends;
      };
      $djambi.find('.controls input').mousedown(function() {
        buttonId = $(this).attr('name');
        if (buttonId == 'ui-replay-autoplay') {
          $djambi.addClass('autoplay'); 
        }
        else {
          $djambi.removeClass('autoplay');
        }
      });
      launchAnimations(true);
      // Suppression de l'affichage des messages après une minute
      if ($('#Messages').length > 0 && $djambi.data('status') != 'finished') {
        setTimeout(function() {$('#Messages').hide('slow');}, 60000);
      }
      // Rafraichissement automatique des données
      $djambi.find('.refresh-button').hide();
      if ($djambi.data('refresh') == 'yes') {
        if (typeof DjambiRefreshInterval !== 'undefined') {
          clearInterval(DjambiRefreshInterval);
        }
        DjambiRefreshInterval = setInterval(function() {
          $grid = $('#DjambiGridFieldset' + gridId);
          if ($grid.data('refresh') == 'no') {
            clearInterval(DjambiRefreshInterval);
            return;
          }
          jQuery.ajax('/djambi/' + gridId + '/check-update/' + $grid.data('version')).done(function(json) {
            result = jQuery.parseJSON(json);
            $block = $('#DjambiActiveGameInfo');
            if ($block.length > 0 && ($block.data('user-faction') != result['user-faction'] || $block.data('status') != result['status'])) {
              $('.refresh-my-djambi-panel a').click();
            }
            else if ($block.length == 0 && $('.refresh-my-djambi-panel a.do-auto-refresh').length == 0) {
              $('.refresh-my-djambi-panel a').click();
            }
            if (result.changed == 0) {
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
              clearInterval(DjambiRefreshInterval);
              $djambigrid.find('.changes').remove();
              for (i in result.moves) {
                move = result.moves[i];
                $cell = $djambigrid.find('.cell[data-coord="'+move.location+'"]');
                $cell.prepend("<div class='changes'></div>");
                new_html = "<div class='change " + move.faction + "' data-order='" + i + "'>";
                if (result.show_moves) {
                  new_html += "<span class='order'>" + move.order + "</span><div class='description'>" + move.description + "</div>";
                }
                new_html += "</div>";
                $cell.find('.changes').prepend(new_html);
                $origin = $djambigrid.find('.cell[data-coord="'+move.origin+'"]');
                if ($origin.attr('data-piece-relocated')) {
                  $origin = $djambigrid.find('.cell[data-piece-relocated="'+move.origin+'"]');
                }
                $origin.attr('data-piece-relocated', move.location).find('div.piece').attr('data-animation-' + i, move.animation);
              }
              for(cell in result.changing_cells) {
                $djambigrid.find('.cell[data-coord="'+cell+'"]').addClass('past-move ' + result.changing_cells[cell]);
              }
              $djambigrid.addClass('animated');
              ends = launchAnimations(false);
              refreshTimeout = setTimeout(function() {
                $('.djambi .refresh-button').show().attr('value', Drupal.t('Refreshing...')).mousedown();
              }, ends);
            }
          });
        }, 10000);
      }
      // Glisser-déposer des pièces
      $djambigrid.find(".cell.std").not(".throne").hover(function() {
        if($(this).parents(".djambigrid tbody").hasClass("dragging")) {$(this).stop().css("background-color","");return;}
        if(!$(this).data("originalBgColor")) {$(this).data("originalBgColor", $(this).css("background-color"));}
        var colors = ["#fea", "#eeb", "#dfb", "#fdb", "#bdf", "#ddf", "#fd6", "#de9", "#fcc", "#fcb"];
        color = colors[Math.floor(Math.random() * colors.length)];
        $(this).stop().animate({backgroundColor: color}, 1000);
        $(this).find('.cell-top-inside').stop().animate({borderBottomColor: color}, 1000);
        $(this).find('.cell-bottom-inside').stop().animate({borderTopColor: color}, 1000);
      },function() {
        if($(this).parents(".djambigrid tbody").hasClass("dragging")) {$(this).stop().css("background-color","");return;}
        origColor = $(this).data("originalBgColor");
        $(this).stop().animate({backgroundColor: origColor}, 1000, function() {
          $(this).css("background-color", "");
          $(this).removeData("originalBgColor");
        });
        $(this).find('.cell-top-inside').stop().animate({borderBottomColor: origColor}, 1000, function() {
          $(this).css("border-bottom-color", "");
        });
        $(this).find('.cell-bottom-inside').stop().animate({borderTopColor: origColor}, 1000, function() {
          $(this).css("border-top-color", "");
        });
      });
      $positionable = $djambigrid.find(".piece.positionable");
      if ($positionable.length == 1) {
        var pos_height = $positionable.height();
      var pos_width = $positionable.width();
        var orig_offset = $positionable.offset();
        $(".djambigrid").mouseenter(function() {
          $(this).bind('mousemove', function(e){
            $positionable.offset({
              left:  e.pageX - pos_width / 2,
              top:   e.pageY - pos_height / 2
            });
          });
        }).mouseleave(function() {
          $(this).unbind('mousemove');
          $positionable.offset(orig_offset);
        });
      }
      var $pieces = $(".piece.movable");
      if ($pieces.length > 0 && $djambigrid.find(".cell.with-selected-piece").length == 0) {
        $pieces.draggable({
          cursor: "move",
          containment: $djambigrid.is("table") ? $djambigrid.children("tbody") : $djambigrid,
              revert: "invalid",
              snap: ".cell-content",
              snapTolerance: 10,
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
                $grid = $(this).parents(".djambigrid");
                if ($grid.is('table')) {
                  var $grid = $(this).parents(".djambigrid tbody");
                }
                $grid.addClass("dragging");
                $(this).data("destination", null).parents(".cell").addClass("unselectable with-selected-piece");
                $(this).children("a").addClass("moved");
                for(var i=0;i<allowedMoves.length;i++) {
                  $djambigrid.find(".cell[data-coord='"+allowedMoves[i]+"']").addClass("selectable").droppable({
                    disabled: false,
                    drop: function(event,ui) {
                      ui.draggable.data("destination", $(this).data("coord"));
                    }
                  });
                }
              },
              stop: function(event,ui) {
                $grid = $(this).parents(".djambigrid");
                if ($grid.is('table')) {
                  var $grid = $(this).parents(".djambigrid tbody");
                }
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
    });
  }};
})(jQuery);