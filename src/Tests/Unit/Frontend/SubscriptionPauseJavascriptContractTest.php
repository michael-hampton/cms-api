<?php

namespace App\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

final class SubscriptionPauseJavascriptContractTest extends TestCase
{
    private string $source;

    public function test_controller_reads_backend_flow_and_endpoint(): void
    {
        self::assertStringContainsString(
            "JSON.parse(trigger.dataset.subscriptionPause || '{}')",
            $this->source,
        );
        self::assertStringContainsString('fetch(this.flow.endpoint', $this->source);
    }

    public function test_duplicate_submissions_are_blocked_and_buttons_are_disabled(): void
    {
        self::assertStringContainsString("this.state === 'submitting'", $this->source);
        self::assertStringContainsString('this.confirmButton.disabled = submitting', $this->source);
        self::assertStringContainsString('this.cancelButton.disabled = submitting', $this->source);
        self::assertStringContainsString("? 'Pausing…'", $this->source);
    }

    public function test_error_keeps_modal_open_and_displays_backend_message(): void
    {
        self::assertStringContainsString("this.state = 'error'", $this->source);
        self::assertStringContainsString("this.showError(error.message", $this->source);
        self::assertStringContainsString("this.message.classList.add('is-visible', 'is-error')", $this->source);
    }

    public function test_success_refreshes_card_state_and_close_restores_focus(): void
    {
        self::assertStringContainsString('window.location.reload()', $this->source);
        self::assertStringContainsString('this.trigger?.focus()', $this->source);
    }

    public function test_expired_session_uses_contextual_login_url(): void
    {
        self::assertStringContainsString('this.modal.dataset.loginUrl', $this->source);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $path = dirname(__DIR__, 3) . '/public/js/subscription-account-pause-controller.js';
        $source = file_get_contents($path);
        self::assertNotFalse($source);
        $this->source = $source;
    }
}
