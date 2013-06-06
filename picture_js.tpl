{footer_script require='jquery'}{literal}
jQuery().ready(function() {
  if (jQuery("#theImage").size() > 0) {
    function save_available_size() {
      var width = jQuery("#theImage").width()
      width -= {/literal}{$asize_width_margin}{literal};

      var docHeight = "innerHeight" in window ? window.innerHeight : document.documentElement.offsetHeight;
      var offset = jQuery("#theImage").offset();
      var height = docHeight - Math.ceil(offset.top);
      height -= {/literal}{$asize_height_margin}{literal};

      document.cookie= 'available_size='+width+'x'+height+';path={/literal}{$COOKIE_PATH}{literal}';
    }

    save_available_size();
    jQuery(window).resize(function() {
      save_available_size();
    });

    jQuery("#aSize").click(function() {
      var is_automatic_size;

      if (jQuery(this).data("checked") == "yes") {
        is_automatic_size = "no";
        jQuery("#aSizeChecked").css("visibility", "hidden");
      }
      else {
        is_automatic_size = "yes";
        jQuery("#aSizeChecked").css("visibility", "visible");
      }

      jQuery(this).data("checked", is_automatic_size);
      document.cookie= 'is_automatic_size='+is_automatic_size+';path={/literal}{$COOKIE_PATH}{literal}';
    });
  }
});
{/literal}{/footer_script}