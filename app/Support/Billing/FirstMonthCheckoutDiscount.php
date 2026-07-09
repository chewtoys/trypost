<?php

declare(strict_types=1);

namespace App\Support\Billing;

use Laravel\Cashier\SubscriptionBuilder;
use RuntimeException;

final class FirstMonthCheckoutDiscount
{
    /**
     * Configure a subscription checkout to charge $1 for the first invoice via
     * a `duration: once` Stripe coupon, so a real charge validates the card
     * instead of a $0 trial authorization. Stripe rejects a Checkout Session
     * that sets both `discounts` and `allow_promotion_codes`, so accounts that
     * skip the paid first month keep the promotion-code field instead.
     *
     * @throws RuntimeException when the paid first month is enabled but no
     *                          coupon is configured — failing loudly beats
     *                          silently charging every new customer full price.
     */
    public static function apply(SubscriptionBuilder $subscription): SubscriptionBuilder
    {
        if (! (bool) config('trypost.billing.require_card_for_trial', true)) {
            return $subscription->allowPromotionCodes();
        }

        $couponId = config('cashier.first_month_coupon_id');

        if (! is_string($couponId) || $couponId === '') {
            throw new RuntimeException(
                'STRIPE_FIRST_MONTH_COUPON_ID must be set when REQUIRE_CARD_FOR_TRIAL is enabled, '
                .'otherwise checkout would charge the full price instead of the $1 first month.'
            );
        }

        return $subscription->withCoupon($couponId);
    }
}
