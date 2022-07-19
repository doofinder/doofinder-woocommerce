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
      const doofinder_regex = /.*\.doofinder\.com/gm;
      //Check that the sender is doofinder
      if (!doofinder_regex.test(event.origin)) return;
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
    if (name === "set_doofinder_data") send_connect_data(data);
  }

  function send_connect_data(data) {
    data["action"] = "doofinder_set_connection_data";
    $.ajax({
      type: "POST",
      dataType: "json",
      url: DoofinderForWC.ajaxUrl,
      data: data,
      success: function (response) {
        if (response.success) {
          confirmLeavePage(false);
          window.location.href = doofinderSetupWizardUrl;
          return;
        } else {
          set_errors(response.errors);
        }
      },
    });
  }

  function set_errors(errors) {
    $(".errors-wrapper ul").remove();
    if (errors) {
      errors_html = "<ul>";
      for (er in errors) {
        error = errors[er];
        errors_html += "<li>" + error + "</li>";
      }
      errors_html += "</ul>";
      $(".errors-wrapper").append(errors_html);
      $(".errors-wrapper").show();
    }
  }
});
