<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Parsers\ContactFormBlockParser;
use App\Parsers\Dtos\ContactFormBlockDto;
use App\Parsers\Renderers\ContactFormBlockRenderer;
use PHPUnit\Framework\TestCase;

class ContactFormBlockParserTest extends TestCase
{
    private ContactFormBlockParser $parser;
    private Site $mockSite;

    public function testGetType(): void
    {
        $this->assertEquals('contact-form', $this->parser->getType());
    }

    public function testParseWithDefaultContactInfo(): void
    {
        SiteContext::set($this->mockSite);

        $data = [
            'title' => 'Contact Us',
            'subtitle' => 'Get in touch',
            'showName' => true,
            'showEmail' => true,
            'showPhone' => false,
            'showSubject' => false,
            'showMessage' => true,
            'submitButtonText' => 'Send Message',
            'requireName' => true,
            'requireEmail' => true,
            'requireMessage' => true
        ];

        $dto = ContactFormBlockDto::fromArray($data);
        $result = $dto->toArray();

        $this->assertEquals('Contact Us', $result['title']);
        $this->assertEquals('Get in touch', $result['subtitle']);
        $this->assertTrue($result['showName']);
        $this->assertTrue($result['showEmail']);
        $this->assertFalse($result['showPhone']);
        $this->assertTrue($result['showMessage']);
        $this->assertEquals('Send Message', $result['submitButtonText']);

        // Check contact info is populated from site
        $this->assertEquals('test@example.com', $result['contact_info']['email']);
        $this->assertEquals('+44 20 1234 5678', $result['contact_info']['phone']);
        $this->assertEquals('123 Test Street', $result['contact_info']['address']['line1']);
        $this->assertEquals('London', $result['contact_info']['address']['city']);
    }

    public function testParseWithBlockLevelOverrides(): void
    {
        SiteContext::set($this->mockSite);

        $data = [
            'title' => 'Contact Us',
            'subtitle' => 'Get in touch',
            'showName' => true,
            'showEmail' => true,
            'submitButtonText' => 'Send',
            'requireName' => true,
            'requireEmail' => true,
            // Block-level overrides
            'override_email' => 'custom@example.com',
            'override_phone' => '+44 20 9999 9999',
            'override_address' => [
                'line1' => '456 Custom Street',
                'city' => 'Manchester',
                'postcode' => 'M1 1AA'
            ]
        ];

        $dto = ContactFormBlockDto::fromArray($data);
        $result = $dto->toArray();

        // Check overrides take precedence
        $this->assertEquals('custom@example.com', $result['contact_info']['email']);
        $this->assertEquals('+44 20 9999 9999', $result['contact_info']['phone']);
        $this->assertEquals('456 Custom Street', $result['contact_info']['address']['line1']);
        $this->assertEquals('Manchester', $result['contact_info']['address']['city']);
    }

    public function testParseWithNoSiteContext(): void
    {
        SiteContext::set(null);

        $data = [
            'title' => 'Contact Us',
            'subtitle' => '',
            'showName' => true,
            'showEmail' => true,
            'submitButtonText' => 'Send',
            'requireName' => true,
            'requireEmail' => true
        ];

        $dto = ContactFormBlockDto::fromArray($data);
        $result = $dto->toArray();

        // Should use default contact info
        $this->assertEquals('hello@example.com', $result['contact_info']['email']);
        $this->assertEquals('+44 20 7123 4567', $result['contact_info']['phone']);
        $this->assertEquals('123 Example Street', $result['contact_info']['address']['line1']);
    }

    public function testParseWithPartialOverrides(): void
    {
        SiteContext::set($this->mockSite);

        $data = [
            'title' => 'Contact Us',
            'showEmail' => true,
            'submitButtonText' => 'Send',
            'requireEmail' => true,
            // Only override email, other fields should come from site
            'override_email' => 'support@example.com'
        ];

        $dto = ContactFormBlockDto::fromArray($data);
        $result = $dto->toArray();

        // Email is overridden
        $this->assertEquals('support@example.com', $result['contact_info']['email']);

        // Phone comes from site
        $this->assertEquals('+44 20 1234 5678', $result['contact_info']['phone']);

        // Address comes from site
        $this->assertEquals('123 Test Street', $result['contact_info']['address']['line1']);
    }

    public function testGenerateDefaultHtmlWithSiteContactInfo(): void
    {
        SiteContext::set($this->mockSite);

        $dto = ContactFormBlockDto::fromArray([
            'title' => 'Get In Touch',
            'subtitle' => 'We are here to help',
            'showName' => true,
            'showEmail' => true,
            'showPhone' => true,
            'showMessage' => true,
            'submitButtonText' => 'Send Message',
            'requireName' => true,
            'requireEmail' => true,
            'requireMessage' => true
        ]);

        $parsedData = $dto->toArray();
        $renderer = new ContactFormBlockRenderer();
        $html = $renderer->render($dto);

        // Check HTML contains site contact information
        $this->assertStringContainsString('test@example.com', $html);
        $this->assertStringContainsString('+44 20 1234 5678', $html);
        $this->assertStringContainsString('123 Test Street', $html);
        $this->assertStringContainsString('Suite 100', $html);
        $this->assertStringContainsString('London', $html);
        $this->assertStringContainsString('SW1A 1AA', $html);

        // Check social links
        $this->assertStringContainsString('https://facebook.com/test', $html);
        $this->assertStringContainsString('https://instagram.com/test', $html);
        $this->assertStringContainsString('https://twitter.com/test', $html);
        $this->assertStringContainsString('https://linkedin.com/test', $html);
    }

    public function testGenerateDefaultHtmlWithOverriddenContactInfo(): void
    {
        SiteContext::set($this->mockSite);

        $dto = ContactFormBlockDto::fromArray([
            'title' => 'Contact Support',
            'showEmail' => true,
            'showPhone' => true,
            'submitButtonText' => 'Send',
            'requireEmail' => true,
            'override_email' => 'support@custom.com',
            'override_phone' => '+44 800 123 456',
            'override_address' => [
                'line1' => '789 Support Ave',
                'city' => 'Birmingham',
                'postcode' => 'B1 1AA'
            ]
        ]);

        $parsedData = $dto->toArray();
        $renderer = new ContactFormBlockRenderer();
        $html = $renderer->render($dto);

        // Check HTML contains overridden contact information
        $this->assertStringContainsString('support@custom.com', $html);
        $this->assertStringContainsString('+44 800 123 456', $html);
        $this->assertStringContainsString('789 Support Ave', $html);
        $this->assertStringContainsString('Birmingham', $html);
        $this->assertStringContainsString('B1 1AA', $html);
    }

    public function testGenerateHtmlWithSidebarContext(): void
    {
        SiteContext::set($this->mockSite);

        $dto = ContactFormBlockDto::fromArray([
            'title' => 'Enquire Now',
            'subtitle' => 'Interested in this property?',
            'showName' => true,
            'showEmail' => true,
            'showPhone' => true,
            'showMessage' => true,
            'showPropertyInterest' => true,
            'submitButtonText' => 'Submit Enquiry',
            'requireName' => true,
            'requireEmail' => true,
            'context' => 'sidebar'
        ]);

        $parsedData = $dto->toArray();
        $renderer = new ContactFormBlockRenderer();
        $html = $renderer->render($dto);

        // Check it's using sidebar template
        $this->assertStringContainsString('contact-form-sidebar', $html);
        $this->assertStringContainsString('property_enquiry', $html);
        $this->assertStringContainsString('Enquire Now', $html);
        $this->assertStringContainsString('Submit Enquiry', $html);
    }

    public function testHtmlEscaping(): void
    {
        SiteContext::set($this->mockSite);

        $parsedData = $this->parser->parse([
            'title' => '<script>alert("xss")</script>Contact',
            'subtitle' => '<img src=x onerror=alert(1)>',
            'showEmail' => true,
            'submitButtonText' => 'Send',
            'requireEmail' => true
        ]);

        // Check that malicious content is properly escaped
        $this->assertEquals(
            htmlspecialchars('<script>alert("xss")</script>Contact'),
            $parsedData['formatted_title']
        );
        $this->assertEquals(
            htmlspecialchars('<img src=x onerror=alert(1)>'),
            $parsedData['formatted_subtitle']
        );
    }

    public function testValidationRules(): void
    {
        $rules = $this->parser->getValidationRules();

        // Check required rules exist
        $this->assertArrayHasKey('title', $rules);
        $this->assertArrayHasKey('subtitle', $rules);
        $this->assertArrayHasKey('showName', $rules);
        $this->assertArrayHasKey('showEmail', $rules);
        $this->assertArrayHasKey('showPhone', $rules);
        $this->assertArrayHasKey('showSubject', $rules);
        $this->assertArrayHasKey('showMessage', $rules);
        $this->assertArrayHasKey('submitButtonText', $rules);
        $this->assertArrayHasKey('successMessage', $rules);
        $this->assertArrayHasKey('recipientEmail', $rules);

        // Check override rules exist
        $this->assertArrayHasKey('override_email', $rules);
        $this->assertArrayHasKey('override_phone', $rules);
        $this->assertArrayHasKey('override_address', $rules);
        $this->assertArrayHasKey('override_social', $rules);
    }

    public function testGenerateDefaultHtmlWithMissingSocialLinks(): void
    {
        $mockSite = $this->createMock(Site::class);
        $mockSite->method('getContactInfo')->willReturn([
            'email' => 'test@example.com',
            'phone' => '+44 20 1234 5678',
            'address' => [
                'line1' => '123 Test Street',
                'city' => 'London',
                'postcode' => 'SW1A 1AA'
            ],
            'social' => [
                'facebook' => '',
                'instagram' => '',
                'twitter' => 'https://twitter.com/test',
                'linkedin' => ''
            ]
        ]);

        SiteContext::set($mockSite);

        $parsedData = $this->parser->parse([
            'title' => 'Contact Us',
            'showEmail' => true,
            'submitButtonText' => 'Send',
            'requireEmail' => true
        ]);

        $html = $this->parser->generateHtml($parsedData);

        // Should only include twitter link
        $this->assertStringContainsString('https://twitter.com/test', $html);

        // Should not have empty social links rendered
        $facebookCount = substr_count($html, 'facebook.com');
        $this->assertEquals(0, $facebookCount);
    }

    public function testGenerateDefaultHtmlWithMissingAddressLine2(): void
    {
        $mockSite = $this->createMock(Site::class);
        $mockSite->method('getContactInfo')->willReturn([
            'email' => 'test@example.com',
            'phone' => '+44 20 1234 5678',
            'address' => [
                'line1' => '123 Test Street',
                'line2' => '', // Empty line 2
                'city' => 'London',
                'postcode' => 'SW1A 1AA'
            ],
            'social' => []
        ]);

        SiteContext::set($mockSite);

        $parsedData = $this->parser->parse([
            'title' => 'Contact Us',
            'showEmail' => true,
            'submitButtonText' => 'Send',
            'requireEmail' => true
        ]);

        $html = $this->parser->generateHtml($parsedData);

        // Should not have extra <br> for empty line2
        $this->assertStringContainsString('123 Test Street', $html);
        $this->assertStringContainsString('London, SW1A 1AA', $html);

        // Count <br> tags in address section - should be fewer
        $addressSection = substr($html, strpos($html, '123 Test Street'), 100);
        $brCount = substr_count($addressSection, '<br>');
        $this->assertLessThanOrEqual(1, $brCount);
    }

    public function testBooleanFieldDefaults(): void
    {
        SiteContext::set($this->mockSite);

        // Test with minimal data - should use defaults
        $data = [
            'title' => 'Contact',
            'submitButtonText' => 'Send'
        ];

        $result = $this->parser->parse($data);

        // Check boolean defaults
        $this->assertTrue($result['showName']);
        $this->assertTrue($result['showEmail']);
        $this->assertFalse($result['showPhone']);
        $this->assertFalse($result['showSubject']);
        $this->assertTrue($result['showMessage']);
        $this->assertFalse($result['showPropertyInterest']);
        $this->assertTrue($result['requireName']);
        $this->assertTrue($result['requireEmail']);
        $this->assertFalse($result['requirePhone']);
        $this->assertFalse($result['requireSubject']);
        $this->assertTrue($result['requireMessage']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ContactFormBlockParser();

        // Create mock site
        $this->mockSite = $this->createMock(Site::class);
        $this->mockSite->method('getContactInfo')->willReturn([
            'email' => 'test@example.com',
            'phone' => '+44 20 1234 5678',
            'address' => [
                'line1' => '123 Test Street',
                'line2' => 'Suite 100',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
                'country' => 'UK'
            ],
            'social' => [
                'facebook' => 'https://facebook.com/test',
                'instagram' => 'https://instagram.com/test',
                'twitter' => 'https://twitter.com/test',
                'linkedin' => 'https://linkedin.com/test'
            ]
        ]);
    }
}