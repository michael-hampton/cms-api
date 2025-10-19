<?php

namespace App\Tests\Unit\Services;

use App\Framework\Support\Collection;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Product;
use App\Services\FooterRenderer;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class FooterRendererTest extends FunctionalTestCase
{
    private $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new FooterRenderer();
    }

    public function testRenderFooterWithBrandSection()
    {
        $menu = new Menu();
        $menu->layout_config = [
            'footer_style' => 'modern',
            'show_brand_section' => true,
            'logo_type' => 'text',
            'logo_main' => 'TEST',
            'logo_sub' => 'COMPANY',
            'footer_description' => 'Test description'
        ];

        $html = $this->renderer->renderFooter($menu);

        $this->assertStringContainsString('TEST', $html);
        $this->assertStringContainsString('COMPANY', $html);
        $this->assertStringContainsString('Test description', $html);
    }

    public function testRenderFooterWithSocialLinks()
    {
        $menu = new Menu();
        $menu->layout_config = [
            'show_brand_section' => true,
            'social_links' => [
                'facebook' => 'https://facebook.com/test',
                'twitter' => 'https://twitter.com/test'
            ]
        ];

        $html = $this->renderer->renderFooter($menu);

        $this->assertStringContainsString('facebook.com/test', $html);
        $this->assertStringContainsString('twitter.com/test', $html);
    }

    public function testRenderFooterWithNewsletterSection()
    {
        $menu = new Menu();
        $menu->layout_config = [
            'show_newsletter' => true,
            'newsletter_title' => 'Subscribe',
            'newsletter_description' => 'Get updates',
            'newsletter_placeholder' => 'Your email',
            'newsletter_button_text' => 'Sign Up'
        ];

        $html = $this->renderer->renderFooter($menu);

        $this->assertStringContainsString('Subscribe', $html);
        $this->assertStringContainsString('Get updates', $html);
        $this->assertStringContainsString('Your email', $html);
        $this->assertStringContainsString('Sign Up', $html);
    }

    public function testRenderFooterWithColumns()
    {
        $menu = Mockery::mock(Menu::class)->makePartial();
        $menu->layout_config = [];

        $item1 = new MenuItem([
            'id' => 1,
            'label' => 'Column Header',
            'target_type' => 'custom',
            'custom_url' => '#',
            'column_group' => 1,
            'is_active' => true
        ]);

        $item2 = new MenuItem([
            'id' => 2,
            'label' => 'Link 1',
            'target_type' => 'custom',
            'custom_url' => '/link1',
            'column_group' => 1,
            'is_active' => true
        ]);

        $menu->shouldReceive('getActiveItemsAttribute')
            ->andReturn(new Collection([$item1, $item2]));

        $html = $this->renderer->renderFooter($menu);

        $this->assertStringContainsString('Column Header', $html);
        $this->assertStringContainsString('Link 1', $html);
        $this->assertStringContainsString('/link1', $html);
    }

    public function testRenderFooterWithLegalLinks()
    {
        $menu = Mockery::mock(Menu::class)->makePartial();

        $menu->shouldReceive('getAttribute')
            ->with('layout_config')
            ->andReturn([
            'brand_name' => 'Test Company',
            'legal_links' => [
                ['label' => 'Privacy', 'url' => '/privacy'],
                ['label' => 'Terms', 'url' => '/terms']
            ]
        ]);

        $menu->shouldReceive('getActiveItemsAttribute')
            ->andReturn(new Collection([]));

        $html = $this->renderer->renderFooter($menu);

        $this->assertStringContainsString('Privacy', $html);
        $this->assertStringContainsString('/privacy', $html);
        $this->assertStringContainsString('Terms', $html);
        $this->assertStringContainsString('/terms', $html);
    }

    public function testRenderFooterWithCopyrightText()
    {
        $menu = new Menu();
        $menu->layout_config = [
            'brand_name' => 'Test Company',
            'copyright_text' => '© {year} {brand}. All rights reserved.'
        ];

        $html = $this->renderer->renderFooter($menu);

        $year = date('Y');
        $this->assertStringContainsString($year, $html);
        $this->assertStringContainsString('Test Company', $html);
    }
}