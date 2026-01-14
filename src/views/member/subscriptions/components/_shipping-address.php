<div class="info-row">
    <span class="info-label">Shipping Address</span>
    <span class="info-value" style="font-size: 14px; line-height: 1.6;">
            <?php
            // First check if subscription has an associated order with shipping address
            $shippingAddress = null;

            if ($activeSubscription->one_time_subscription_id) {
                // Look up the order associated with this subscription
                $order = \App\Models\Order::where('one_time_subscription_id', $activeSubscription->id)
                        ->first();

                if ($order) {
                    // Try to get address from order's relationship first
                    if ($order->shipping_address_id) {
                        $shippingAddress = \App\Models\Address::find($order->shipping_address_id);
                    } elseif ($order->shipping_address && is_array($order->shipping_address)) {
                        // Use the stored address array from order
                        echo htmlspecialchars($order->shipping_address['address_line_1'] ?? $order->shipping_address['line1'] ?? '') . '<br>';
                        if (!empty($order->shipping_address['address_line_2'] ?? $order->shipping_address['line2'] ?? '')) {
                            echo htmlspecialchars($order->shipping_address['address_line_2'] ?? $order->shipping_address['line2']) . '<br>';
                        }
                        echo htmlspecialchars($order->shipping_address['city'] ?? '') . ', ';
                        echo htmlspecialchars($order->shipping_address['postcode'] ?? '');
                        $shippingAddress = 'displayed'; // Flag that we've shown it
                    }
                }
            }

            // If no order address found, fall back to member's default shipping address
            if (!$shippingAddress || $shippingAddress !== 'displayed') {
                if ($shippingAddress) {
                    // We have an Address object
                    echo htmlspecialchars($shippingAddress->address_line_1) . '<br>';
                    if ($shippingAddress->address_line_2) {
                        echo htmlspecialchars($shippingAddress->address_line_2) . '<br>';
                    }
                    echo htmlspecialchars($shippingAddress->city) . ', ' . htmlspecialchars($shippingAddress->postcode);
                } else {
                    // Try to get default shipping address from member
                    $defaultAddress = \App\Models\Address::where('member_id', $member->id)
                            ->where('site_id', \App\Framework\Support\SiteContext::getId())
                            ->where('is_default', true)
                            ->whereIn('type', ['shipping', 'both'])
                            ->first();

                    if ($defaultAddress) {
                        echo htmlspecialchars($defaultAddress->address_line_1) . '<br>';
                        if ($defaultAddress->address_line_2) {
                            echo htmlspecialchars($defaultAddress->address_line_2) . '<br>';
                        }
                        echo htmlspecialchars($defaultAddress->city) . ', ' . htmlspecialchars($defaultAddress->postcode);
                    } else {
                        ?>
                        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/addresses"
                           style="color: #667eea;">
                            Add shipping address
                        </a>
                        <?php
                    }
                }
            }
            ?>
        </span>
</div>