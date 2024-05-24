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
        if (response.status === "timed-out") {
          $("#df-indexing-status").remove();
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
        nonce: Doofinder.nonce
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
          nonce: Doofinder.nonce
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
        nonce: Doofinder.nonce
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

  let CustomAttributesHandler = {
    valid: true,
    init: function () {
      //Initialize select2
      $(".df-select-2").select2();
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
          $(this)
            .select2("destroy")
            .replaceWith(
              '<input class="df-attribute-text" placeholder="Enter the metafield key" type="text" name="doofinder_for_wp_custom_attributes[new][attribute]" value="" />'
            );
        } else {
          $(this)
            .parent()
            .next()
            .find(".df-field-text")
            .val(default_attribute_name)
            .trigger("change");
        }
      });

      /**
       * Removes a field
       */
      $(".df-delete-attribute-btn").on("click", function (ev) {
        ev.preventDefault();
        $(this).closest("tr").remove();
        $(".df-field-text").trigger("change");
      });

      /**
       * Submits the form to add the new field
       */
      $(".df-add-attribute-btn").on("click", function (ev) {
        ev.preventDefault();
        $("#submit").click();
      });

      /**
       * Validate the field
       */
      $(".df-field-text").on("change", function () {
        CustomAttributesHandler.validate_custom_fields(this);
      });

      /**
       * Check if the form is valid
       */

      $("#df-settings-form").on("submit", function (ev) {
        if (!CustomAttributesHandler.valid) {
          ev.preventDefault();
        }
      });
    },
    clear_errors: function (elem) {
      $(elem).closest("tr").find(".errors").empty();
    },
    add_error: function (elem, message) {
      CustomAttributesHandler.clear_errors(elem);

      $(elem)
        .closest("tr")
        .find(".errors")
        .append(`<p class='error'>${message}</p>`);
      $(elem).addClass("invalid");
      CustomAttributesHandler.valid = false;
    },
    validate_custom_fields: function (elem) {
      CustomAttributesHandler.valid = true;
      let field_name = $(elem).val();

      var regex = /^[_.]/;

      if (regex.test(field_name)) {
          CustomAttributesHandler.add_error(elem, "The name cannot start with _ or .");
      }
      
      let field_id = $(elem).attr("id");
      //Get the existing fields excluding the new one
      let existing_fields = $(".df-field-text")
        .map(function (index, elem) {
          if (field_id != $(elem).attr("id")) {
            return $(elem).val();
          }
        })
        .toArray();

      if (Doofinder.RESERVED_CUSTOM_ATTRIBUTES_NAMES.includes(field_name)) {
        let message =
          Doofinder.reserved_custom_attributes_error_message.replace(
            /%field_name%/g,
            field_name
          );
        CustomAttributesHandler.add_error(elem, message);
      }

      if (existing_fields.includes(field_name)) {
        let message =
          Doofinder.duplicated_custom_attributes_error_message.replace(
            /%field_name%/g,
            field_name
          );
        CustomAttributesHandler.add_error(elem, message);
      }

      if (CustomAttributesHandler.valid) {
        $(elem).removeClass("invalid");
        $(elem).closest("tr").find(".errors").empty();
        $("#df-settings-form input[type=submit]").attr("disabled", false);
        //Check if the current change may solve any other existing invalid field
        $(".df-field-text.invalid").trigger("change");
      } else {
        $("#df-settings-form input[type=submit]").attr("disabled", true);
      }
    },
  };

  if ($(".doofinder-for-wp-attributes").length > 0) {
    //initialize CustomAttributesHandler
    CustomAttributesHandler.init();
  }
});
