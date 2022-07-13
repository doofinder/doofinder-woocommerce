jQuery(() => {
  const $ = jQuery.noConflict();

  let connectModal;
  let checkInterval;

  const popupCenter = ({ url, title, w, h }) => {
    const dualScreenLeft =
      window.screenLeft !== undefined ? window.screenLeft : window.screenX;
    const dualScreenTop =
      window.screenTop !== undefined ? window.screenTop : window.screenY;
    const width = window.innerWidth
      ? window.innerWidth
      : document.documentElement.clientWidth
      ? document.documentElement.clientWidth
      : screen.width;
    const height = window.innerHeight
      ? window.innerHeight
      : document.documentElement.clientHeight
      ? document.documentElement.clientHeight
      : screen.height;
    const systemZoom = width / window.screen.availWidth;
    const left = (width - w) / 2 / systemZoom + dualScreenLeft;
    const top = (height - h) / 2 / systemZoom + dualScreenTop;
    const newWindow = window.open(
      url,
      title,
      `
        scrollbars=yes,
        width=${w / systemZoom},
        height=${h / systemZoom},
        top=${top},
        left=${left}
      `
    );

    if (window.focus) {
      newWindow.focus();
    }

    return newWindow;
  };

  const confirmLeavePage = (active = true) => {
    if (active) {
      window.onbeforeunload = () => "";
    } else {
      window.onbeforeunload = () => null;
    }
  };
  /*
  const ajaxCheckData = () => {
    jQuery
      .ajax({
        type: "POST",
        dataType: "json",
        url: DoofinderForWC.ajaxUrl,
        data: { action: "doofinder_for_wc_check_data" },
      })
      .then((response) => {
        if (!response.success) {
          console.error("ajaxCheckData - Something went wrong");
          return;
        }

        console.info("ajaxCheckData - Status: " + response.data.status);

        if (
          response.data.status !== "saved" &&
          response.data.status !== "error"
        ) {
          console.info("ajaxCheckData - checking again...");
          return;
        }

        $(".errors-wrapper ul").remove();
        if (response.data.status === "error") {
          errors = "<ul>";
          for (er in response.data.error) {
            error = response.data.error[er];
            errors += "<li>" + error + "</li>";
          }
          errors += "</ul>";
          $(".errors-wrapper").append(errors);
          $(".errors-wrapper").show();
        }

        if (response.data.status === "saved") {
          clearInterval(checkInterval);
          confirmLeavePage(false);
          console.info("ajaxCheckData - reloading page...");
          window.location.href = doofinderSetupWizardUrl;
        }
      });
  };
*/
  $("#doofinder-for-wc-index-button").click(() => {
    const progressBarWrapper = $(".dfwc-setup-step__progress-bar-wrapper").get(
      0
    );
    progressBarWrapper.classList.add("active");
    progressBarWrapper.parentElement.classList.add("indexing");
  });

  $(".open-window").click((e) => {
    const pageType = $(e.currentTarget).attr("data-type");
    doofinderAdminEndpoint = "https://pedro-doomanager.ngrok.doofinder.com";

    connectModal = popupCenter({
      url: `${doofinderAdminEndpoint}/plugins/${pageType}/woocommerce?email=${doofinderConnectEmail}&token=${doofinderConnectToken}`,
      title: "DoofinderConnect",
      w: 600,
      h: 700,
    });

    //clearInterval(checkInterval);
    //checkInterval = setInterval(ajaxCheckData, 1000);
  });

  window.addEventListener(
    "message",
    (event) => {
      // Do we trust the sender of this message?  (might be
      // different from what we originally opened, for example).
      if (event.origin !== doofinderAdminEndpoint) return;
      console.log(event.source, "Source");
      console.log(event.data, "data");
      if (event.data) {
        data = event.data.split("|");
        event_name = data[0];
        event_data = JSON.parse(atob(data[1]));
        processMessage(event_name, event_data);
      }
    },
    false
  );

  function processMessage(name, data) {
    switch (name) {
      case "set_doofinder_data":
        console.log("Send ajax request to save the doofinder connection data");
        console.log(data);
        send_connect_data(data);
        break;

      default:
        break;
    }
  }

  function send_connect_data(data) {
    data["action"] = "doofinder_set_connection_data";
    jQuery
      .ajax({
        type: "POST",
        dataType: "json",
        url: DoofinderForWC.ajaxUrl,
        data: data,
      })
      .then((response) => {
        if (response.success) {
          confirmLeavePage(false);
          window.location.href = doofinderSetupWizardUrl;
          return;
        } else {
          $(".errors-wrapper ul").remove();
          if (response.errors) {
            errors = "<ul>";
            for (er in response.errors) {
              error = response.errors[er];
              errors += "<li>" + error + "</li>";
            }
            errors += "</ul>";
            $(".errors-wrapper").append(errors);
            $(".errors-wrapper").show();
          }
        }
      });
  }
});
