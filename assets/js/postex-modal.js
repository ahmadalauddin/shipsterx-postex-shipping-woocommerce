(function($){
    $(document).ready(function(){
        // Listen for click on the custom order action button
        $(document).on('click', '.postex_create', function(e){
            e.preventDefault();
            if ($('#postex-modal').length === 0) {
                createPostExModal();
            }
        });
        
        function createPostExModal() {
            if (!PostExWC.order_data) {
                alert('Order data not available');
                return;
            }
            
            const orderData = PostExWC.order_data;
            const itemsHtml = orderData.items.map(item => 
                `<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #eee;">
                    <span>${item.quantity}x ${item.name}</span>
                    <span>PKR ${item.price.toFixed(2)}</span>
                </div>`
            ).join('');
            
            const modalHtml = `
            <div id="postex-modal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
              <div style="background:#fff;padding:2em;min-width:600px;max-width:800px;max-height:90vh;overflow-y:auto;position:relative;border-radius:8px;box-shadow:0 2px 16px rgba(0,0,0,0.2);">
                <button id="postex-modal-close" style="position:absolute;top:10px;right:10px;background:none;border:none;font-size:1.5em;cursor:pointer;">&times;</button>
                <h2 style="margin-top:0;">Create PostEx Order</h2>
                
                <!-- Order Items Section -->
                <div style="margin-bottom:20px;">
                    <h3 style="margin-bottom:10px;">Order Items</h3>
                    <div style="border:1px solid #ddd;padding:10px;border-radius:4px;max-height:150px;overflow-y:auto;">
                        ${itemsHtml}
                        <div style="display:flex;justify-content:space-between;padding:8px 0;border-top:2px solid #ccc;margin-top:8px;font-weight:bold;">
                            <span>Total</span>
                            <span>PKR ${orderData.total}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Editable Fields -->
                <div id="postex-modal-content">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;">
                        <div>
                            <label for="postex-customer-name" style="display:block;margin-bottom:5px;font-weight:bold;">Customer Name:</label>
                            <input type="text" id="postex-customer-name" value="${orderData.customer_name}" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                        </div>
                        <div>
                            <label for="postex-customer-phone" style="display:block;margin-bottom:5px;font-weight:bold;">Phone:</label>
                            <input type="text" id="postex-customer-phone" value="${orderData.customer_phone}" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                        </div>
                        <div>
                            <label for="postex-delivery-address" style="display:block;margin-bottom:5px;font-weight:bold;">Delivery Address:</label>
                            <textarea id="postex-delivery-address" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;height:60px;">${orderData.delivery_address}</textarea>
                        </div>
                        <div>
                            <label for="postex-city-name" style="display:block;margin-bottom:5px;font-weight:bold;">City:</label>
                            <input type="text" id="postex-city-name" value="${orderData.city_name}" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                        </div>
                        <div>
                            <label for="postex-invoice-payment" style="display:block;margin-bottom:5px;font-weight:bold;">Order Value (PKR):</label>
                            <input type="number" id="postex-invoice-payment" min="0" step="0.01" value="${orderData.total}" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                        </div>
                        <div>
                            <label for="postex-weight" style="display:block;margin-bottom:5px;font-weight:bold;">Weight (kg):</label>
                            <input type="number" id="postex-weight" min="0.1" step="0.1" value="${orderData.default_weight}" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                        </div>
                        <div style="grid-column:span 2;">
                            <label for="postex-dimensions" style="display:block;margin-bottom:5px;font-weight:bold;">Dimensions (L x W x H in cm):</label>
                            <input type="text" id="postex-dimensions" value="${orderData.default_dimensions}" placeholder="15x10x5" style="width:200px;padding:8px;border:1px solid #ddd;border-radius:4px;">
                        </div>
                    </div>
                    
                    <div style="text-align:center;margin-top:20px;">
                        <button id="postex-create-order" style="background:#007cba;color:#fff;padding:12px 30px;border:none;border-radius:4px;cursor:pointer;font-size:16px;margin-right:10px;">Create PostEx Order</button>
                        <button id="postex-modal-close-btn" style="background:#666;color:#fff;padding:12px 30px;border:none;border-radius:4px;cursor:pointer;font-size:16px;">Cancel</button>
                    </div>
                    <div id="postex-modal-status" style="margin-top:15px;text-align:center;font-weight:bold;"></div>
                </div>
              </div>
            </div>`;
            $('body').append(modalHtml);
        }
        
        // Close modal
        $(document).on('click', '#postex-modal-close, #postex-modal-close-btn', function(){
            $('#postex-modal').remove();
        });
        
        // AJAX handler for Create Order with all editable fields
        $(document).on('click', '#postex-create-order', function(){
            const orderData = {
                action: 'postex_wc_create_order',
                nonce: PostExWC.nonce,
                order_id: PostExWC.order_id,
                customer_name: $('#postex-customer-name').val().trim(),
                customer_phone: $('#postex-customer-phone').val().trim(),
                delivery_address: $('#postex-delivery-address').val().trim(),
                city_name: $('#postex-city-name').val().trim(),
                invoice_payment: parseFloat($('#postex-invoice-payment').val()) || 0,
                weight: parseFloat($('#postex-weight').val()) || 0.5,
                dimensions: $('#postex-dimensions').val().trim()
            };
            
            // Basic validation
            if (!orderData.customer_name || !orderData.customer_phone || !orderData.delivery_address || !orderData.city_name) {
                $('#postex-modal-status').css('color','red').text('Please fill in all required fields.');
                return;
            }
            
            if (orderData.invoice_payment <= 0 || orderData.weight <= 0) {
                $('#postex-modal-status').css('color','red').text('Order value and weight must be greater than 0.');
                return;
            }
            
            $('#postex-modal-status').css('color','blue').text('Creating PostEx order...');
            $('#postex-create-order').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                dataType: 'json',
                data: orderData,
                success: function(response) {
                    $('#postex-create-order').prop('disabled', false);
                    if (response.success) {
                        $('#postex-modal-status').css('color','green').text(
                            'Order created successfully! Tracking #: ' + response.data.tracking_number +
                            ' | Status: ' + response.data.order_status
                        );
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        $('#postex-modal-status').css('color','red').text(
                            response.data && response.data.message ? response.data.message : 'Failed to create order.'
                        );
                    }
                },
                error: function(xhr) {
                    $('#postex-create-order').prop('disabled', false);
                    $('#postex-modal-status').css('color','red').text('Connection error. Please try again.');
                }
            });
        });
        
        // Close modal when clicking outside
        $(document).on('click', '#postex-modal', function(e){
            if (e.target === this) {
                $('#postex-modal').remove();
            }
        });
    });
})(jQuery);