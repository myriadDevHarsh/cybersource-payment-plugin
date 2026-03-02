jQuery(function ($) {

  let unifiedInstance = null;
  let jwtLoaded = false;

  function showLoader() {
    $('#nab-loader').show();
  }

  function hideLoader() {
    $('#nab-loader').fadeOut(300);
  }

  function setProcessingMessage() {

    const total = parseFloat(nab_ajax.total).toFixed(2);
    const orderNumber = nab_ajax.order_id;

    const message = `
        Processing payment of <strong>$${total}</strong>
        for Order Number: <strong>${orderNumber}</strong>
    `;

    $('#nab-processing-text').html(message);
    hideLoader();
  }

  async function loadUnifiedCheckout(jwt) {

    if (unifiedInstance) return;

    const jwtParts = jwt.split('.');
    const jwtPayload = JSON.parse(atob(jwtParts[1]));

    const clientLibrary = jwtPayload.ctx[0].data.clientLibrary;
    const clientLibraryIntegrity = jwtPayload.ctx[0].data.clientLibraryIntegrity;

    if (!document.querySelector(`script[src="${clientLibrary}"]`)) {
      const script = document.createElement('script');
      script.type = 'text/javascript';
      script.async = true;
      script.src = clientLibrary;
      script.integrity = clientLibraryIntegrity;
      script.crossOrigin = "anonymous";
      document.head.appendChild(script);

      await new Promise(resolve => {
        script.onload = resolve;
      });
    }

    const container = document.querySelector('#nab-card-container');
    if (!container) alert('not container');

    try {

      const accept = await Accept(jwt);

      const up = await accept.unifiedPayments(false);

      const tt = await up.show({
        containers: {
          paymentSelection: "#nab-card-container",
          paymentScreen: '#nab-card-container-embedded'
        }
      });


      const completeResponse = await up.complete(tt);
      const verify = await $.ajax({
        url: nab_ajax.ajax_url,
        type: "POST",
        dataType: "json",
        data: {
          action: "nab_verify_payment",
          security: nab_ajax.nonce,
          order_id: nab_ajax.order_id,
          jwt: completeResponse
        }
      });

      if (verify.success) {
        window.location.href = nab_ajax.redirect;
      } else {
        alert("Error processing payment");
      }

      unifiedInstance = up;
      jwtLoaded = true;

    } catch (error) {
      hideLoader();
      console.error("Unified Checkout error:", error);
    }
  }

  function getCaptureContext() {

    if (jwtLoaded) {
      return Promise.resolve({ success: true });
    }

    return $.ajax({
      url: nab_ajax.ajax_url,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'nab_get_capture_context',
        security: nab_ajax.nonce,
        order_id: nab_ajax.order_id
      }
    });
  }

  async function initNabGateway() {

    if (unifiedInstance) return;

    setProcessingMessage();
    showLoader();

    try {

      const response = await getCaptureContext();

      if (!response.success || !response.data?.jwt) {
        hideLoader();
        console.error(response.data?.message || 'JWT error');
        return;
      }

      const jwt = response.data.jwt;

      await loadUnifiedCheckout(jwt);

    } catch (error) {
      hideLoader();
      console.error("Capture context error:", error);
    }
  }

  initNabGateway();

});
