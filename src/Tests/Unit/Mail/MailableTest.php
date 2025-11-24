<?php

namespace App\Tests\Unit\Mail;

use App\Framework\Mail\Mailable;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class MailableTest extends FunctionalTestCase
{
    private TestMailable $mailable;

    public function setUp(): void
    {
        $this->mailable = new TestMailable();

        parent::setUp();
    }

    public function testSubjectCanBeSet(): void
    {
        $this->mailable->subject('Test Subject');
        $this->assertEquals('Test Subject', $this->mailable->subject);
    }

    public function testFromCanBeSet(): void
    {
        $this->mailable->from('test@example.com', 'Test Name');
        $this->assertEquals('test@example.com', $this->mailable->from);
        $this->assertEquals('Test Name', $this->mailable->fromName);
    }

    public function testToCanBeSet(): void
    {
        $this->mailable->to('recipient@example.com', 'Recipient Name');
        $this->assertCount(1, $this->mailable->to);
        $this->assertEquals('recipient@example.com', $this->mailable->to[0]['address']);
    }

    public function testMultipleRecipientsCanBeAdded(): void
    {
        $this->mailable
            ->to('recipient1@example.com')
            ->to('recipient2@example.com');

        $this->assertCount(2, $this->mailable->to);
    }

    public function testCcCanBeSet(): void
    {
        $this->mailable->cc('cc@example.com');
        $this->assertCount(1, $this->mailable->cc);
    }

    public function testBccCanBeSet(): void
    {
        $this->mailable->bcc('bcc@example.com');
        $this->assertCount(1, $this->mailable->bcc);
    }

    public function testReplyToCanBeSet(): void
    {
        $this->mailable->replyTo('reply@example.com');
        $this->assertCount(1, $this->mailable->replyTo);
    }

    public function testAttachmentCanBeAdded(): void
    {
        $this->mailable->attach('/path/to/file.pdf');
        $this->assertCount(1, $this->mailable->attachments);
        $this->assertEquals('/path/to/file.pdf', $this->mailable->attachments[0]['path']);
    }

    public function testDataAttachmentCanBeAdded(): void
    {
        $this->mailable->attachData('test data', 'test.txt');
        $this->assertCount(1, $this->mailable->attachments);
        $this->assertEquals('test data', $this->mailable->attachments[0]['data']);
        $this->assertEquals('test.txt', $this->mailable->attachments[0]['name']);
    }

    public function testViewCanBeSet(): void
    {
        $this->mailable->view('test.view', ['key' => 'value']);
        $this->assertEquals('test.view', $this->mailable->view);
        $this->assertEquals('value', $this->mailable->viewData['key']);
    }

    public function testMarkdownCanBeSet(): void
    {
        $this->mailable->markdown('test.markdown', ['key' => 'value']);
        $this->assertEquals('test.markdown', $this->mailable->markdown);
        $this->assertEquals('value', $this->mailable->viewData['key']);
    }

    public function testWithAddsViewData(): void
    {
        $this->mailable->with('key', 'value');
        $this->assertEquals('value', $this->mailable->viewData['key']);
    }

    public function testWithAcceptsArray(): void
    {
        $this->mailable->with(['key1' => 'value1', 'key2' => 'value2']);
        $this->assertEquals('value1', $this->mailable->viewData['key1']);
        $this->assertEquals('value2', $this->mailable->viewData['key2']);
    }

    public function testBuildIsCalledAndReturnsChainableSelf(): void
    {
        $result = $this->mailable->build();
        $this->assertInstanceOf(TestMailable::class, $result);
    }

    public function testMarkdownConversion(): void
    {
        // Use raw markdown content for testing
        $this->mailable->markdown = '# Heading';
        $this->mailable->viewData = [];
        $html = $this->mailable->render();

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Heading', $html);
    }

    public function testMarkdownBoldConversion(): void
    {
        $this->mailable->markdown = '**bold text**';
        $this->mailable->viewData = [];
        $html = $this->mailable->render();

        $this->assertStringContainsString('<strong>bold text</strong>', $html);
    }

    public function testMarkdownItalicConversion(): void
    {
        $this->mailable->markdown = '*italic text*';
        $this->mailable->viewData = [];
        $html = $this->mailable->render();

        $this->assertStringContainsString('<em>italic text</em>', $html);
    }

    public function testMarkdownLinkConversion(): void
    {
        $this->mailable->markdown = '[Link Text](http://example.com)';
        $this->mailable->viewData = [];
        $html = $this->mailable->render();

        $this->assertStringContainsString('href="http://example.com"', $html);
        $this->assertStringContainsString('Link Text', $html);
    }

    public function testMarkdownButtonConversion(): void
    {
        $this->mailable->markdown = '@button(Click Me, http://example.com)';
        $this->mailable->viewData = [];
        $html = $this->mailable->render();

        $this->assertStringContainsString('Click Me', $html);
        $this->assertStringContainsString('http://example.com', $html);
        $this->assertStringContainsString('href="http://example.com"', $html);
    }

    public function testEmailTemplateWrapsContent(): void
    {
        $this->mailable->markdown = 'Test content';
        $this->mailable->viewData = [];
        $html = $this->mailable->render();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<body>', $html);
        $this->assertStringContainsString('Test content', $html);
    }

    public function testMarkdownWithVariables(): void
    {
        $this->mailable->markdown = 'Hello **{{ $name }}**';
        $this->mailable->viewData = ['name' => 'John'];
        $html = $this->mailable->render();

        $this->assertStringContainsString('Hello', $html);
        $this->assertStringContainsString('John', $html);
    }

    public function testIsViewPathDetectsRawMarkdown(): void
    {
        $this->mailable->markdown = '# Heading';
        $this->assertFalse($this->mailable->isViewPath('# Heading'));

        $this->assertFalse($this->mailable->isViewPath('**bold**'));
        $this->assertFalse($this->mailable->isViewPath('@button(text, url)'));
    }
}

class TestMailable extends Mailable
{
    public function build(): self
    {
        return $this->subject('Test Subject');
    }

    // Make protected methods public for testing
    public function isViewPath(string $markdown): bool
    {
        return parent::isViewPath($markdown);
    }
}