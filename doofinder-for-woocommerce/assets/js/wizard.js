jQuery(() => {
    const $ = jQuery.noConflict();
    const button = document.getElementById('doofinder-for-wc-index-button');
    if (!button) {
        return;
    }
    const progressBarWrapper = document.querySelector('.dfwc-setup-step__progress-bar-wrapper');
    const initShowProgressBar = () => {
        $(button).click(() => {
            progressBarWrapper.classList.add('active');
            progressBarWrapper.parentElement.classList.add('indexing');
        });
    };
    initShowProgressBar();
});
const popupCenter = ({ url, title, w, h }) => {
    const dualScreenLeft = window.screenLeft !== undefined ? window.screenLeft : window.screenX;
    const dualScreenTop = window.screenTop !== undefined ? window.screenTop : window.screenY;
    const width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
    const height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;
    const systemZoom = width / window.screen.availWidth;
    const left = (width - w) / 2 / systemZoom + dualScreenLeft;
    const top = (height - h) / 2 / systemZoom + dualScreenTop;
    const newWindow = window.open(url, title, `
      scrollbars=yes,
      width=${w / systemZoom}, 
      height=${h / systemZoom}, 
      top=${top}, 
      left=${left}
      `);
    if (window.focus)
        newWindow.focus();
    return newWindow;
};
let connectModal;
jQuery(() => {
    const $ = jQuery.noConflict();
    const button = document.querySelectorAll('.open-window');
    if (!button) {
        return;
    }
    const confirmLeavePage = (active = true) => {
        if (active) {
            window.onbeforeunload = () => '';
        }
        else {
            window.onbeforeunload = () => null;
        }
    };
    const ajaxCheckData = () => {
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: DoofinderForWC.ajaxUrl,
            data: {
                action: 'doofinder_for_wc_check_data'
            }
        })
            .then((response) => {
            if (!response.success) {
                console.error('ajaxCheckData - Something went wrong');
                return;
            }
            console.info('ajaxCheckData - Status: ' + response.data.status);
            if (response.data.status !== 'saved' && response.data.status !== 'error') {
                console.info('ajaxCheckData - checking again...');
                setTimeout(ajaxCheckData, 500);
                return;
            }
            confirmLeavePage(false);
            console.info('ajaxCheckData - reloading page...');
            window.location.href = doofinderSetupWizardUrl;
        });
    };
    const initShowNewWindow = () => {
        $(button).click((e) => {
            const pageType = $(e.currentTarget).attr('data-type');
            connectModal = popupCenter({
                url: `https://app.doofinder.com/plugins/${pageType}/woocommerce?email=${doofinderConnectEmail}&token=${doofinderConnectToken}&return_path=${doofinderConnectReturnPath}`,
                title: 'DoofinderConnect',
                w: 600,
                h: 700
            });
            ajaxCheckData();
        });
    };
    initShowNewWindow();
});
