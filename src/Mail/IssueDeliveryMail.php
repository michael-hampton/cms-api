<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;

/**
 * Thin Mailable wrapper for pre-built issue HTML.
 * Content generation is handled entirely by NewsletterContentBuilder —
 * this class only carries the rendered output into the mail pipeline.
 */
final class IssueDeliveryMail extends Mailable
{
    public function __construct(
        private readonly string $recipient,
        public string           $subject,
        private readonly string $renderedHtml
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $this->to($this->recipient)
            ->subject($this->subject);

// Store the pre-built HTML so render() returns it directly.
// We bypass the markdown/view pipeline — content is already rendered.
        $this->view = null;
        $this->markdown = null;

        return $this;
    }

    public function render(): string
    {
        return $this->renderedHtml;
    }
}