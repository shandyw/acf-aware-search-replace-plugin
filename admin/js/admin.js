(function ($) {
  "use strict";

  /**
   * All of the code for your admin-facing JavaScript source
   * should reside in this file.
   *
   * Note: It has been assumed you will write jQuery code here, so the
   * $ function reference has been prepared for usage within the scope
   * of this function.
   *
   * This enables you to define handlers, for when the DOM is ready:
   *
   * $(function() {
   *
   * });
   *
   * When the window is loaded:
   *
   * $( window ).load(function() {
   *
   * });
   *
   * ...and/or other possibilities.
   *
   * Ideally, it is not considered best practise to attach more than a
   * single DOM-ready or window-load handler for a particular page.
   * Although scripts in the WordPress core, Plugins and Themes may be
   * practising this, we should strive to set a better example in our own work.
   */

  $(function () {
    var $input = $("#replace");
    var $wrap = $("#acfsr-replace-btn-wrap");

    if (!$input.length || !$wrap.length) return;

    function toggle() {
      var hasVal = $.trim($input.val()).length > 0;
      $wrap.toggle(hasVal);
    }

    // initial + live
    toggle();
    $input.on("input change keyup", toggle);
  });

  $(function () {
    var $sel = $("#acfsr-per-page-select");
    var $wrap = $("#acfsr-per-page-custom-wrap");
    var $input = $("#acfsr-per-page-custom");

    if ($sel.length) {
      function render() {
        var v = $sel.val();
        if (v === "custom") {
          $wrap.show();
          // If custom empty, seed with a sane default
          if (!$input.val()) $input.val(2000);
        } else {
          $wrap.hide();
          // Ensure numeric per_page is cleared so server uses the select value
          $input.val("");
        }
      }
      $sel.on("change", render);
      render();
    }
  });

  $(function () {
    // Auto-show/hide Replace button (you may already have this)
    var $replace = $("#replace"),
      $wrap = $("#acfsr-replace-btn-wrap");
    function toggleReplace() {
      $wrap.toggle($.trim($replace.val()).length > 0);
    }
    $replace.on("input change keyup", toggleReplace);
    toggleReplace();

    // Auto-submit on Per Page changes
    var $form = $("form.filters");
    var $sel = $("#acfsr-per-page-select");
    var $custom = $("#acfsr-per-page-custom");
    var $perH = $("#acfsr_per_hidden");
    var $pageH = $("#acfsr_page_hidden");

    function submitWithPer(per) {
      if (!$form.length) return;
      $perH.val(per);
      $pageH.val(1); // reset to first page on page-size change
      $form.trigger("submit");
    }

    if ($sel.length) {
      $sel.on("change", function () {
        var v = $(this).val();
        if (v === "custom") {
          $("#acfsr-per-page-custom-wrap").show();
          $custom.focus();
        } else {
          $("#acfsr-per-page-custom-wrap").hide();
          submitWithPer(parseInt(v, 10));
        }
      });
    }
    if ($custom.length) {
      $custom.on("change", function () {
        var v = parseInt($(this).val(), 10) || 2000;
        submitWithPer(v);
      });
    }
  });
})(jQuery);
