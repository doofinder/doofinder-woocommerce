class DoofinderAddToCartError extends Error {
    constructor(reason, status = "") {
        const message = "Error adding an item to the cart. Reason: " + reason + ". Status code: " + status;
        super(message);
        this.name = "DoofinderAddToCartError";
    }
}  

(function ($) {
    $(document).ready(function () {
        document.addEventListener("doofinder.cart.add", function (event) {
            const { item_id, amount, statusPromise } = event.detail;
            addProductToCart(item_id, amount, statusPromise);
        });
    });

    function addProductToCart(item_id, amount, statusPromise) {
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
                    wc_add_to_cart(
                        response.product,
                        response.variation,
                        amount,
                        statusPromise
                    );
                } else {
                    statusPromise.reject(new DoofinderAddToCartError("Empty cart response"));
                    window.location = response.product_url;
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                statusPromise.reject(new DoofinderAddToCartError(thrownError, xhr.status));
            }
        });
    }

    function wc_add_to_cart(product_id, variation_id, product_qty, statusPromise) {
        var data = {
            action: "doofinder_ajax_add_to_cart",
            product_id: product_id,
            quantity: product_qty,
            variation_id: variation_id,
            nonce: df_cart.nonce
        };

        $fakebutton = $("<input type='button'/>");

        $(document.body).trigger("adding_to_cart", [$fakebutton, data]);

        $.ajax({
            type: "post",
            url: df_cart.ajax_url,
            data: data,
            success: function (response) {
                if (response.error & response.product_url) {
                    statusPromise.reject(new DoofinderAddToCartError("Invalid product or cart"));
                    window.location = response.product_url;
                } else if (typeof wc_add_to_cart_params !== "undefined") {
                    //Woocommerce cart is included, trigger add to cart event
                    $(document.body).trigger("added_to_cart", [
                        response.fragments,
                        response.cart_hash,
                        $fakebutton,
                    ]);
                    statusPromise.resolve("The item has been successfully added to the cart.");
                } else {
                    //No woocommerce cart, reload the page
                    statusPromise.reject(new DoofinderAddToCartError("No Woocommerce cart was found."));
                    location.reload();
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                statusPromise.reject(new DoofinderAddToCartError(thrownError, xhr.status));
            }
        });
    }
})(jQuery);
