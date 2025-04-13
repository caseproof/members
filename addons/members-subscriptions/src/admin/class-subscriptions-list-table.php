    /**
     * Column amount
     *
     * @param object $item
     * @return string
     */
    public function column_amount($item) {
        $amount = floatval($item->price);
        
        // Get currency symbol and formatting
        $currency_symbol = '$'; // Default
        $currency_position = 'before'; // Default
        
        // Check if WordPress Currency settings are available
        if (function_exists('get_woocommerce_currency_symbol')) {
            $currency_symbol = get_woocommerce_currency_symbol();
        }
        
        // Format the amount based on currency position
        if ($currency_position === 'before') {
            $formatted_amount = $currency_symbol . number_format_i18n($amount, 2);
        } else {
            $formatted_amount = number_format_i18n($amount, 2) . $currency_symbol;
        }
        
        // Add tax amount if available
        if (isset($item->tax_amount) && floatval($item->tax_amount) > 0) {
            $tax_amount = floatval($item->tax_amount);
            $total_amount = $amount + $tax_amount;
            
            if ($currency_position === 'before') {
                $formatted_total = $currency_symbol . number_format_i18n($total_amount, 2);
            } else {
                $formatted_total = number_format_i18n($total_amount, 2) . $currency_symbol;
            }
            
            $formatted_amount = sprintf(
                '<span class="amount-base">%s</span> <span class="amount-tax">(+%s%s tax)</span><br><span class="amount-total">%s total</span>',
                $formatted_amount,
                $currency_symbol,
                number_format_i18n($tax_amount, 2),
                $formatted_total
            );
        }
        
        // Add subscription details
        $details = '';
        
        // If it's a recurring subscription, show period
        if (!empty($item->period)) {
            $period_type = $item->period_type;
            $period = $item->period;
            
            $details = sprintf(
                '<span class="members-recurring-details">%s</span>',
                Subscriptions\format_subscription_period($period, $period_type)
            );
        }
        
        return $formatted_amount . '<br>' . $details;
    }