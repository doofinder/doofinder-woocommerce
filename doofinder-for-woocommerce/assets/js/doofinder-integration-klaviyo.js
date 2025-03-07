/** START INTEGRATION WITH KLAVIYO **/
window.addEventListener('load', async (event) => {
  if ('undefined' !== typeof klaviyo && false === await klaviyo.isIdentified() && 'undefined' !== typeof klUser && "" !== klUser.current_user_email) {
    const email = klUser.current_user_email;
    const companyId = await klaviyo.account();
    let userId = window.localStorage.getItem('df-random-userid');
    userId = JSON.parse(userId);

    klaviyo.identify({"email": email});

    try {
      const response = await fetch('https://a.klaviyo.com/client/profiles?company_id=' + companyId, {
        method: 'POST',
        headers: {
          accept: 'application/vnd.api+json',
          revision: '2025-01-15',
          'content-type': 'application/vnd.api+json'
        },
        body: JSON.stringify({
          data: {
            type: "profile",
            attributes: {
              email: email,
              external_id: userId
            }
          }
        })
      });

      if (!response.ok) {
        console.error('Failed to send data to Klaviyo:', await response.text());
      }
    } catch (error) {
      console.error('Failed to send data to Klaviyo:', error);
    }
  }
});
/** END INTEGRATION WITH KLAVIYO **/
