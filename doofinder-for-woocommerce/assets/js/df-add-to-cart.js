(function ($) {
  $(document).ready(function () {
    document.addEventListener("doofinder.cart.add", function (event) {
      const { item_id, amount } = event.detail;
      addProductToCart(item_id, amount);
    });
  });

  function addProductToCart(item_id, amount) {
    amount = !amount ? 1 : parseInt(amount);
    item_id = parseInt(item_id);

    $.ajax({
      type: "post",
      url: df_cart.ajax_url,
      dataType: "json",
      data: {
        action: "doofinder_get_product_info",
        id: item_id,
      },
      success: function (response) {
        if (response.add_to_cart) {
          wc_add_to_cart(response.product, response.variation, amount);
        } else {
          window.location = response.product_url;
          return;
        }
      },
    });
  }

  function wc_add_to_cart(product_id, variation_id, product_qty) {
    var data = {
      action: "doofinder_ajax_add_to_cart",
      product_id: product_id,
      quantity: product_qty,
      variation_id: variation_id,
    };

    $fakebutton = $("<input type='button'/>");

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
          if (typeof(wc_add_to_cart_params) != 'undefined') {
            //Woocommerce cart is included, trigger add to cart event
            $(document.body).trigger("added_to_cart", [
              response.fragments,
              response.cart_hash,
              $fakebutton,
            ]);
          } else {
            //No woocommerce cart, reload the page
            location.reload();
          }
        }
      },
    });
  }
})(jQuery);
