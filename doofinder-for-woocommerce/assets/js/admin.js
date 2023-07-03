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
            },
        });
    };

    if (Doofinder.show_indexing_notice === "true") {
        indexingCheckInterval = setInterval(ajaxIndexingStatus, 10000);
    }

    let UpdateOnSaveHandler = function () {
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
                    $(".update-result-wrapper").fadeOut()
                    $(".update-result-wrapper").empty();
                }, 5000);
            },
        });
    };

    let force_update_btn = $("#force-update-on-save");
    force_update_btn.on("click", UpdateOnSaveHandler);

    /*
    TODO: Implement notice dismiss ajax action

    $(".notice.is-dismissable .notice-dismissible").on(
        "click",
        function () {
            let notice_id = $(this).attr("id");
            console.log("calling dismiss notice");
            $.ajax({
                type: "post",
                dataType: "json",
                url: ajaxurl,
                data: {
                    action: "doofinder_dismiss_notice",
                    notice_id: notice_id
                },
                success: function (response) {
                    console.log("Notice dismissed")
                },
            });
        }
    );
    */
});
