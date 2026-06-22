<?php

namespace App\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

final class SavedCardsJavascriptContractTest extends TestCase
{
    private string $source;

    public function test_saved_cards_support_flat_payment_method_payloads(): void
    {
        self::assertStringContainsString('function normaliseSavedCard(card)', $this->source);
        self::assertStringContainsString('const details = card.card || card;', $this->source);
        self::assertStringContainsString('brand: details.brand', $this->source);
        self::assertStringContainsString('last4: details.last4', $this->source);
        self::assertStringContainsString('exp_month: details.exp_month', $this->source);
        self::assertStringContainsString('exp_year: details.exp_year', $this->source);
    }

    public function test_load_saved_cards_accepts_wrapped_and_legacy_responses(): void
    {
        self::assertStringContainsString('data.data?.payment_methods', $this->source);
        self::assertStringContainsString('data.payment_methods', $this->source);
        self::assertStringContainsString('methods.map(normaliseSavedCard)', $this->source);
    }

    public function test_display_saved_cards_does_not_read_nested_card_properties_directly(): void
    {
        self::assertStringNotContainsString('card.card.brand', $this->source);
        self::assertStringNotContainsString('card.card.last4', $this->source);
        self::assertStringNotContainsString('card.card.exp_month', $this->source);
        self::assertStringNotContainsString('card.card.exp_year', $this->source);
        self::assertStringContainsString('savedCard.brand', $this->source);
        self::assertStringContainsString('savedCard.last4', $this->source);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $source = file_get_contents(dirname(__DIR__, 3) . '/public/js/saved-cards.js');
        self::assertNotFalse($source);
        $this->source = $source;
    }
}
