jQuery(() => {
  const $ = jQuery.noConflict();
  $(document).ready(function () {
    document.addEventListener("doofinder.cart.add", function (event) {
      //Show the loader
      $("body").append('<div id="df-spinner" class="loading"></div>');
      const { item_id, amount } = event.detail;
      addProductToCart(item_id, amount);
    });
  });

  function closeDoofinderLayer() {
    $('button[dfd-click="close-layer"').click();
  }

  function addProductToCart(item_id, amount) {
    amount = !amount ? 1 : parseInt(amount);
    item_id = parseInt(item_id);

    $.ajax({
      type: "get",
      url: df_cart.item_info_endpoint + item_id,
      dataType: "json",
      success: function (response) {
        if (response.add_to_cart) {
          wc_add_to_cart(item_id, response.default_variation, amount);
        } else {
          window.location = response.product_url;
          return;
        }
      },
    });
  }

  function wc_add_to_cart(product_id, variation_id, product_qty) {
    var data = {
      action: "woocommerce_ajax_add_to_cart",
      product_id: product_id,
      product_sku: "",
      quantity: product_qty,
      variation_id: variation_id,
    };

    $fakebutton = $("<input type='button'/>");

    closeDoofinderLayer();
    $(document.body).trigger("adding_to_cart", [$fakebutton, data]);

    $.ajax({
      type: "post",
      url: df_cart.ajax_url,
      data: data,
      success: function (response) {
        if (response.error & response.product_url) {
          window.location = response.product_url;
          return;
        } else {
          if (wc_add_to_cart_params != undefined) {
            //Woocommerce cart is included, trigger add to cart event
            $(document.body).trigger("added_to_cart", [
              response.fragments,
              response.cart_hash,
              $fakebutton,
            ]);

            console.log($fakebutton);
            $("#df-spinner").remove();
          } else {
            //No woocommerce cart, reload the page
            location.reload();
          }
        }
      },
    });
  }
});
