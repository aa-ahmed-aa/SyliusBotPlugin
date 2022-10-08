$("#page").on("change", async (event) => {
   const response = await $.ajax(`/admin/persistent_menu/${event.target.value}`);

   $('#list_products').val(response['list_products']);
   $('#order_summery').val(response['order_summery']);
   $('#my_cart').val(response['my_cart']);
   $('#empty_cart').val(response['empty_cart']);
   $('#checkout').val(response['checkout']);
   $('#bot_id').val(response['bot_id']);
   $('#facebook_page').val(response['facebook_page']);
   $('#get_started_text').val(response['get_started_text']);
});