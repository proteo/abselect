/**
 * @file
 * "Authored by (select)" companion behaviors.
 */

(function (Drupal, $, once) {
  "use strict";

  Drupal.behaviors.AuthoredBySelect = {
    attach: function attach(context) {
      const $authorName = $(once('authored_by_select', '.authored-by-name', context));
      $(once('authored_by_select', '.authored-by-select', context)).on('change', function() {
        const author = $(this).find("option:selected").text();
        $authorName.text(author);
      });
    }
  };

}(Drupal, jQuery, once));
