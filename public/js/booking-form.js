/**
 * Pose Booking - Frontend JS (Direct Booking)
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        $('.session-type-card').on('click', function () {
            var $card = $(this);
            var wcProduct = $card.data('wc');
            var productType = $card.data('type');
            var productUrl = $card.data('url');
            var sessionName = $card.find('h4').text();

            if (!wcProduct) {
                alert('يرجى ربط نوع الجلسة بمنتج WooCommerce أولاً');
                return;
            }

            // Handle Variable Products (Redirect to Product Page)
            if (productType === 'variable' && productUrl) {
                window.location.href = productUrl;
                return;
            }

            // Simple Products (Direct Add to Cart)
            $('.session-type-card').removeClass('selected');
            $card.addClass('selected processing');
            $('body').css('cursor', 'wait');

            // Add loading text if possible without breaking layout, or just opacity
            $card.css('opacity', '0.6');

            $.ajax({
                url: poseBooking.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pose_add_to_cart',
                    nonce: poseBooking.nonce,
                    wc_product: wcProduct,
                    session_name: sessionName
                },
                success: function (response) {
                    if (response.success) {
                        window.location.href = response.data.checkout_url;
                    } else {
                        alert(response.data || 'حدث خطأ في الإضافة للسلة');
                        resetCard($card);
                    }
                },
                error: function () {
                    alert('حدث خطأ في الاتصال');
                    resetCard($card);
                }
            });
        });
    });

    function resetCard($card) {
        $card.removeClass('processing').css('opacity', '1');
        $('body').css('cursor', 'default');
    }

})(jQuery);
