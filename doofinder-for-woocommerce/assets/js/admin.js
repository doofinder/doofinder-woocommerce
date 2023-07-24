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
                    $("input[name='doofinder_for_wp_enable_js_layer']").attr("checked", true)
                    clearInterval(indexingCheckInterval);
                }
            },
        });
    };

    if (Doofinder.show_indexing_notice === "true") {
        indexingCheckInterval = setInterval(ajaxIndexingStatus, 10000);
    }

    let UpdateOnSaveHandler = function () {
        force_update_btn.attr('disabled', true);
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
                $(".update-result-wrapper")
                    .hide()
                    .empty()
                    .append(message)
                    .fadeIn();

                setTimeout(function () {
                    $(".update-result-wrapper").fadeOut();
                    $(".update-result-wrapper").empty();
                    force_update_btn.attr('disabled', false);
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
            let notice_id = $(this).parents('.notice.doofinder').attr("id");
            $.ajax({
                type: "post",
                dataType: "json",
                url: ajaxurl,
                data: {
                    action: "doofinder_notice_dismiss",
                    notice_id: notice_id,
                }
            });
        }
    );
});
