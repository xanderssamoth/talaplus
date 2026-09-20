<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class ApiTranslationTest extends TestCase
{
    public function test_new_api_entities_are_available_in_french_and_english(): void
    {
        $entities = ['gift', 'gift_transaction', 'media_progress', 'wallet'];
        $actions = ['created', 'deleted', 'updated', 'not_found', 'find_success', 'find_all_success'];

        foreach (['en', 'fr'] as $locale) {
            foreach ($entities as $entity) {
                foreach ($actions as $action) {
                    $this->assertTrue(Lang::has("api.entities.{$entity}.{$action}", $locale));
                }
            }
        }
    }

    public function test_payment_wallet_gift_and_service_messages_are_translated(): void
    {
        $keys = [
            'ai.conversation_not_found',
            'ai.file_attachment_not_implemented',
            'ai.not_implemented',
            'ai.openai_connection_failed',
            'ai.openai_connection_success',
            'ai.pong',
            'ai.unknown_tool',
            'cart.invalid_order_prices',
            'cart.invalid_payment_data',
            'cart.prices_not_convertible',
            'cart.purchase_not_authorized',
            'cart.user_currency_required',
            'cart.view_not_authorized',
            'exchange_rate.currency_not_supported',
            'exchange_rate.request_failed',
            'exchange_rate.unavailable',
            'gift.coin_price_invalid',
            'gift.invalid_pricing',
            'gift.media_owner_missing',
            'gift.quantity_invalid',
            'gift.receiver_not_found',
            'gift.self_not_allowed',
            'payment.card_urls_required',
            'payment.card_field_required',
            'payment.callback_order_number_missing',
            'payment.callback_payment_not_found',
            'payment.check_gateway_not_configured',
            'payment.flexpay_rejected',
            'payment.gateway_not_configured',
            'payment.initiation_not_available',
            'payment.invalid_amount',
            'payment.invalid_currency',
            'payment.invalid_type',
            'payment.order_number_missing',
            'payment.order_number_required',
            'payment.payment_order_number_missing',
            'payment.phone_required',
            'payment.request_failed',
            'payment.required_attributes',
            'payment.required_attribute',
            'payment.service_unavailable',
            'payment.synchronized_success',
            'payment.transaction_details_missing',
            'payment.transaction_not_found',
            'payment.user_id_invalid',
            'profile.avatar_updated',
            'validation.number_of_stars_required',
            'wallet.coin_amount_invalid',
            'wallet.coin_currency_missing',
            'wallet.coin_price_invalid',
            'wallet.credit_amount_invalid',
            'wallet.debit_amount_invalid',
            'wallet.insufficient_coins',
            'wallet.invalid_coin_package',
            'wallet.not_found_for_user',
            'wallet.payment_not_coin_purchase',
            'wallet.payment_pricing_invalid',
            'wallet.payment_pricing_missing',
            'wallet.payment_user_missing',
        ];

        foreach (['en', 'fr'] as $locale) {
            foreach ($keys as $key) {
                $this->assertTrue(Lang::has("api.{$key}", $locale));
            }
        }
    }
}
