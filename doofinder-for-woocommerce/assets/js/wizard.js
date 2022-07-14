jQuery(() => {
  const $ = jQuery.noConflict();

  let connectModal;

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

  $("#doofinder-for-wc-index-button").click(() => {
    const progressBarWrapper = $(".dfwc-setup-step__progress-bar-wrapper").get(
      0
    );
    progressBarWrapper.classList.add("active");
    progressBarWrapper.parentElement.classList.add("indexing");
  });

  $(".open-window").click((e) => {
    const pageType = $(e.currentTarget).attr("data-type");
    connectModal = popupCenter({
      url: `${doofinderAdminPath}/plugins/${pageType}/woocommerce?email=${doofinderConnectEmail}&token=${doofinderConnectToken}`,
      title: "DoofinderConnect",
      w: 600,
      h: 700,
    });
  });

  window.addEventListener(
    "message",
    (event) => {
      //Check that the sender is doofinder
      if (event.origin !== doofinderAdminPath) return;
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
        send_connect_data(data);
        break;

      default:
        break;
    }
  }

  function send_connect_data(data) {
    data["action"] = "doofinder_set_connection_data";
    $.ajax({
      type: "POST",
      dataType: "json",
      url: DoofinderForWC.ajaxUrl,
      data: data,
    }).then((response) => {
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
