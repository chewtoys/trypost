<?php

declare(strict_types=1);

use App\Models\Account;
use App\Support\Billing\FirstMonthCheckoutDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->account = Account::factory()->create();
});

test('applies the first month coupon when a card is required for trial', function () {
    config([
        'trypost.billing.require_card_for_trial' => true,
        'cashier.first_month_coupon_id' => 'TRIAL1USD',
    ]);

    $subscription = $this->account->newSubscription(Account::SUBSCRIPTION_NAME, 'price_monthly_test');

    FirstMonthCheckoutDiscount::apply($subscription);

    expect($subscription->couponId)->toBe('TRIAL1USD')
        ->and($subscription->promotionCodeId)->toBeNull()
        ->and($subscription->allowPromotionCodes)->toBeFalse();
});

test('allows promotion codes instead of a coupon when a card is not required for trial', function () {
    config(['trypost.billing.require_card_for_trial' => false]);

    $subscription = $this->account->newSubscription(Account::SUBSCRIPTION_NAME, 'price_monthly_test');

    FirstMonthCheckoutDiscount::apply($subscription);

    expect($subscription->allowPromotionCodes)->toBeTrue()
        ->and($subscription->couponId)->toBeNull();
});
