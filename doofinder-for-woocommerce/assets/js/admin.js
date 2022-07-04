jQuery(() => {
  const $ = jQuery.noConflict();
  class ProgressBar {
    constructor(bar) {
      this.bar = bar.querySelector("[data-bar]");
      this.status = document.getElementById("progress-value");
    }
    set(value) {
      if (value < 0) {
        value = 0;
      }
      if (value > 100) {
        value = 100;
      }
      this.bar.style.width = `${value}%`;
      if (this.status) {
        this.status.innerText = `${Math.ceil(value)}`;
      }
    }
  }
  const button = document.getElementById("doofinder-for-wc-index-button");
  const spinner = document.getElementById("doofinder-for-wc-spinner");
  const progressBarElement = document.getElementById(
    "doofinder-for-wc-progress-bar"
  );
  const progressBarStatusElement = document.getElementById(
    "doofinder-for-wc-progress-bar-status"
  );
  const additionalMessagesElement = document.getElementById(
    "doofinder-for-wc-additional-messages"
  );
  const indexingError = document.getElementById(
    "doofinder-for-wc-indexing-error"
  );
  if (!button) {
    return;
  }
  const progressBar = new ProgressBar(progressBarElement);
  const maxRetries = 3;
  let errorCount = 0;
  const maxTimeout = 5 * 60 * 1000;
  let currentTimeout = 0;
  const indexContentTimeout = 5000;
  let preparing = true;
  const ajaxIndexContent = () => {
    console.info("ajaxIndexContent - start");
    if (preparing) {
      setProgressBarStatus(false, true);
    }
    console.info(
      'ajaxIndexContent - Current Language: "' + doofinderCurrentLanguage + '"'
    );
    $.ajax({
      type: "POST",
      dataType: "json",
      url: DoofinderForWC.ajaxUrl,
      data: {
        action: "doofinder_for_wc_index_content",
        lang: doofinderCurrentLanguage,
      },
    }).then((response) => {
      console.info("ajaxIndexContent - Ajax response");
      console.info(response);
      if (!response.success) {
        handleError(response);
        return;
      }
      errorCount = 0;
      currentTimeout = 0;
      if ("progress" in response.data) {
        updateProgressBar(response);
      }
      if ("message" in response.data) {
        showAdditionalMessages(response);
      }
      if (!response.data.completed) {
        ajaxIndexContent();
        return;
      }
      confirmLeavePage(false);
      setMessageCookie();
      window.location.reload();
    });
    console.info("ajaxIndexContent - end");
  };
  const ajaxCreateSearchEngines = () => {
    console.info("ajaxCreateSearchEngines - start");
    setProgressBarStatus(false, false, true);
    $.ajax({
      type: "POST",
      dataType: "json",
      url: DoofinderForWC.ajaxUrl,
      data: {
        action: "doofinder_for_wc_process_step_3",
        lang: doofinderCurrentLanguage,
        process_step: "3",
      },
    }).then((response) => {
      console.info("ajaxCreateSearchEngines - Ajax response");
      console.info(response);
      if (!response.success) {
        handleError(response);
        return;
      }
      if ("message" in response.data) {
        showAdditionalMessages(response);
      }
      ajaxIndexContent();
    });
    console.info("ajaxCreateSearchEngines - End");
  };
  const handleError = (response) => {
    if (response.data && response.data.status === "indexing_in_progress") {
      currentTimeout += indexContentTimeout;
    } else {
      errorCount++;
    }
    if ("message" in response.data) {
      showAdditionalMessages(response);
    }
    if (
      errorCount > maxRetries ||
      currentTimeout > maxTimeout ||
      response.data.error
    ) {
      if (
        response.data.status !== "invalid_search_engine" &&
        response.data.status !== "not_authenticated"
      ) {
        indexingError.classList.add("active");
      }
      button.disabled = false;
      spinner.style.display = "";
      spinner.style.visibility = "";
      errorCount = 0;
      currentTimeout = 0;
      setProgressBarStatus();
      preparing = true;
      confirmLeavePage(false);
    } else {
      setTimeout(() => {
        ajaxIndexContent();
      }, indexContentTimeout);
    }
  };
  const updateProgressBar = (response) => {
    progressBar.set(response.data.progress);
    setProgressBarStatus(true);
    preparing = false;
  };
  const showAdditionalMessages = (response) => {
    const errorClass = "doofinder-for-wc-indexing-error";
    additionalMessagesElement.classList.add("active");
    if (response.data.error) {
      additionalMessagesElement.classList.add(errorClass);
    } else {
      additionalMessagesElement.classList.remove(errorClass);
    }
    additionalMessagesElement.innerText = response.data.message;
  };
  const setProgressBarStatus = (
    indexing = false,
    preparing = false,
    creatingEngines = false
  ) => {
    console.info("setProgressBarStatus");
    const indexingStatus = progressBarStatusElement.querySelector(".indexing");
    const preparingStatus =
      progressBarStatusElement.querySelector(".preparing");
    const creatingEnginesStatus =
      progressBarStatusElement.querySelector(".creating-engines");
    if (indexing) {
      indexingStatus.classList.add("active");
    } else {
      indexingStatus.classList.remove("active");
    }
    if (preparing) {
      preparingStatus.classList.add("active");
    } else {
      preparingStatus.classList.remove("active");
    }
    if (creatingEngines) {
      creatingEnginesStatus.classList.add("active");
    } else {
      creatingEnginesStatus.classList.remove("active");
    }
  };
  const confirmLeavePage = (active = true) => {
    if (active) {
      window.onbeforeunload = () => "";
    } else {
      window.onbeforeunload = () => null;
    }
  };
  const setMessageCookie = () => {
    document.cookie = "doofinder_wc_show_success_message=true";
  };
  const initAjaxIndexContent = () => {
    window.initAjaxIndexContentLoaded = true;
    $(button).click(() => {
      indexingError.classList.remove("active");
      button.disabled = true;
      spinner.style.display = "inline-block";
      spinner.style.visibility = "visible";
      confirmLeavePage();
      if (
        typeof createSearchEnginesBeforeIndexing !== "undefined" &&
        createSearchEnginesBeforeIndexing
      ) {
        ajaxCreateSearchEngines();
      } else {
        ajaxIndexContent();
      }
    });
  };
  initAjaxIndexContent();
});
jQuery(() => {
  const $ = jQuery.noConflict();
  const cancelButton = document.querySelector(
    "#doofinder-for-wc-cancel-indexing"
  );
  if (!cancelButton) {
    return;
  }
  cancelButton.addEventListener("click", (e) => {
    e.preventDefault();
    $.ajax({
      type: "POST",
      dataType: "json",
      url: DoofinderForWC.ajaxUrl,
      data: {
        action: "doofider_for_wc_cancel_indexing",
      },
    }).then(() => {
      window.location.reload();
    });
  });
});
jQuery(() => {
  const deleteButtons = document.querySelectorAll(
    ".doofinder-for-wc-delete-attribute-btn"
  );
  if (!deleteButtons.length) {
    return;
  }
  deleteButtons.forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      button.closest("tr").remove();
    });
  });
});
jQuery(() => {
  const skipButtons = document.querySelectorAll(".dfwc-setup-skip");
  const currentForm = document.querySelector(".dfwc-setup-finished");
  if (!skipButtons.length && !currentForm) {
    return;
  }
  skipButtons.forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      currentForm.submit();
    });
  });
});
jQuery(() => {
  const inputUrls = document.querySelectorAll(".dfwc-url-input");
  if (!inputUrls.length) {
    return;
  }
  inputUrls.forEach((item) => {
    const urlPattern = new RegExp(
      /^(?:http(s)?:\/\/)?[\w.-]+(?:\.[\w\.-]+)+[\w\-\._~:/?#[\]@!\$&'\(\)\*\+,;=.]+$/
    );
    item.onblur = inputBlur;
    function inputBlur() {
      if (urlPattern.test(String(item.value).toLowerCase()) == true) {
        item.value = item.value.replace("https://", "").replace("http://", "");
        item.value = "https://" + item.value;
      }
    }
  });
});
