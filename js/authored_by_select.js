/**
 * @file
 * "Authored by (select)" companion behaviors.
 */

(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.AuthoredBySelect = {
    attach: function attach(context) {
      const $authorName = $(".authored-by-name", context);
      $(".authored-by-select", context).once('authored_by_select').on('change', function() {
        const author = $(this).find("option:selected").text();
        $authorName.text(author);
      });
    }
  };

})(jQuery, Drupal);
