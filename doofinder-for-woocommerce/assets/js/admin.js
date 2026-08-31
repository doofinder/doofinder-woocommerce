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
          clearInterval(indexingCheckInterval);
        }
        if (response.status === "timed-out" || response.status === "failed") {
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

  let CreateSearchEngineHandler = function () {
    create_search_engine_btn.attr("disabled", true);
    create_search_engine_spinner.addClass("is-active");

    $.ajax({
      type: "post",
      dataType: "json",
      url: ajaxurl,
      data: {
        action: "doofinder_create_search_engine",
        lang: create_search_engine_btn.data("lang") || "",
        nonce: Doofinder.nonce
      },
      success: function (response) {
        create_search_engine_spinner.removeClass("is-active");

        if (response.success && response.data && response.data.hashid) {
          $("#doofinder-search-engine-hash").val(response.data.hashid).attr("readonly", true);
          $(".create-search-engine-result-wrapper")
            .hide()
            .empty()
            .append("Search Engine created!")
            .fadeIn();
          create_search_engine_btn.remove();
          create_search_engine_spinner.remove();
          return;
        }

        $(".create-search-engine-result-wrapper")
          .hide()
          .empty()
          .append("Error creating the Search Engine, please try again later")
          .fadeIn();
        create_search_engine_btn.attr("disabled", false);
      },
      error: function () {
        create_search_engine_spinner.removeClass("is-active");
        $(".create-search-engine-result-wrapper")
          .hide()
          .empty()
          .append("Error creating the Search Engine, please try again later")
          .fadeIn();
        create_search_engine_btn.attr("disabled", false);
      },
    });
  };

  let create_search_engine_btn = $("#doofinder-create-search-engine");
  let create_search_engine_spinner = $("#doofinder-create-search-engine-spinner");
  create_search_engine_btn.on("click", CreateSearchEngineHandler);

});
