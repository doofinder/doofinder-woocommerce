jQuery(function () {
  let $ = jQuery.noConflict();
  let indexingCheckInterval = null;
  let ajaxIndexingStatus = function () {
    $.ajax({
      type: "post",
      dataType: "json",
      url: ajaxurl,
      data: {
        action: "doofinder_check_indexing_status",
      },
      success: function (response) {
        if (response.status === "processed") {
          $(".indexation-status").toggleClass("processing processed");
          //Enable Layer switch
          $("input[name='doofinder_for_wp_enable_js_layer']").attr(
            "checked",
            true
          );
          clearInterval(indexingCheckInterval);
        }
      },
    });
  };

  if (Doofinder.show_indexing_notice === "true") {
    indexingCheckInterval = setInterval(ajaxIndexingStatus, 10000);
  }

  let UpdateOnSaveHandler = function () {
    force_update_btn.attr("disabled", true);
    $.ajax({
      type: "post",
      dataType: "json",
      url: ajaxurl,
      data: {
        action: "doofinder_force_update_on_save",
      },
      success: function (response) {
        message = "Error updating your data, please try again layer";
        if (response.success) {
          message = "Your data is being updated...";
        }
        $(".update-result-wrapper").hide().empty().append(message).fadeIn();

        setTimeout(function () {
          $(".update-result-wrapper").fadeOut();
          $(".update-result-wrapper").empty();
          force_update_btn.attr("disabled", false);
        }, 5000);
      },
    });
  };

  let force_update_btn = $("#force-update-on-save");
  force_update_btn.on("click", UpdateOnSaveHandler);

  $("body").on(
    "click",
    ".notice.doofinder.is-dismissible .notice-dismiss",
    function () {
      let notice_id = $(this).parents(".notice.doofinder").attr("id");
      $.ajax({
        type: "post",
        dataType: "json",
        url: ajaxurl,
        data: {
          action: "doofinder_notice_dismiss",
          notice_id: notice_id,
        },
      });
    }
  );

  let ResetCredentialsHandler = function () {
    reset_credentials_btn.attr("disabled", true);
    $.ajax({
      type: "post",
      dataType: "json",
      url: ajaxurl,
      data: {
        action: "doofinder_reset_credentials",
      },
      success: function (response) {
        message = "Error updating your data, please try again layer";
        if (response.success) {
          message = "Your data is being updated...";
        }
        $(".update-result-wrapper").hide().empty().append(message).fadeIn();

        setTimeout(function () {
          $(".update-result-wrapper").fadeOut();
          $(".update-result-wrapper").empty();
          reset_credentials_btn.attr("disabled", false);
        }, 5000);
      },
    });
  };

  let reset_credentials_btn = $("#doofinder-reset-credentials");
  reset_credentials_btn.on("click", ResetCredentialsHandler);

  /**
   * This event listener is used to automatically populate the field name when
   * the user selects an option.
   */
  $(".df-attribute-select").on("change", function () {
    let selected_option = $(this).find("option:selected");

    let default_attribute_name = selected_option.data("field-name");
    let attribute_type = selected_option.data("type");
    $(this).parent().next().find(".df-field-type").val(attribute_type);

    if (attribute_type === "metafield") {
      $(this).replaceWith(
        '<input class="df-attribute-text" placeholder="Enter the metafield name" type="text" name="doofinder_for_wp_custom_attributes[new][attribute]" value="" />'
      );
    } else {
      $(this)
        .parent()
        .next()
        .find(".df-field-text")
        .val(default_attribute_name);
    }
  });

  $(".df-delete-attribute-btn").on("click", function (ev) {
    ev.preventDefault();
    $(this).closest("tr").remove();
  });

  $(".df-add-attribute-btn").on("click", function (ev) {
    ev.preventDefault();
    $("#submit").click();
  });
});
