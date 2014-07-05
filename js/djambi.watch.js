(function ($, window, Drupal, drupalSettings) {
  Drupal.behaviors.djambiWatching = {attach: function() {
    Drupal.ajax.prototype.beforeSerialize = function (element, options) {
      if (this.$form) {
        var settings = this.settings || drupalSettings;
        Drupal.detachBehaviors(this.$form.get(0), settings, 'serialize');
      }
      var pageState = drupalSettings.ajaxPageState;
      options.data['ajax_page_state[theme]'] = pageState.theme;
      options.data['ajax_page_state[theme_token]'] = pageState.theme_token;
      for (var cssFile in pageState.css) {
        if (pageState.css.hasOwnProperty(cssFile)) {
          options.data['ajax_page_state[css][' + cssFile + ']'] = 1;
        }
      }
      for (var jsFile in pageState.js) {
        if (pageState.js.hasOwnProperty(jsFile)) {
          options.data['ajax_page_state[js][' + jsFile + ']'] = 1;
        }
      }
    };
  }};
}(jQuery, this, Drupal, drupalSettings));