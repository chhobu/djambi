Drupal.behaviors.Djambi = {attach: function(context) {(function ($) {
  $(".djambigrid td.std").not(".throne").hover(function() {
    if(!$(this).data("originalBgColor")) {$(this).data("originalBgColor", $(this).css("background-color"));}
      $(this).stop().animate({ backgroundColor: "#999"}, 1000);  
    },function() {
      $(this).stop().animate({ backgroundColor: $(this).data("originalBgColor") }, 1000);  
    });
  })(jQuery);
}};