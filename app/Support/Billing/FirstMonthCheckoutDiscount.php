<?php

declare(strict_types=1);

namespace App\Support\Billing;

use Laravel\Cashier\SubscriptionBuilder;

final class FirstMonthCheckoutDiscount
{
    /**
     * Configure a subscription checkout to charge $1 for the first invoice via
     * a `duration: once` Stripe coupon, so a real charge validates the card
     * instead of a $0 trial authorization. Stripe rejects a Checkout Session
     * that sets both `discounts` and `allow_promotion_codes`, so accounts that
     * skip the paid first month keep the promotion-code field instead.
     */
    public static function apply(SubscriptionBuilder $subscription): SubscriptionBuilder
    {
        if ((bool) config('trypost.billing.require_card_for_trial', true)) {
            return $subscription->withCoupon(config('cashier.first_month_coupon_id'));
        }

        return $subscription->allowPromotionCodes();
    }
}
