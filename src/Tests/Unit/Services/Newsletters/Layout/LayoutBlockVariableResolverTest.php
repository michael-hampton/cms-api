<?php

namespace App\Tests\Unit\Services\Newsletters\Layout;

use App\Models\Newsletter;
use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\Layout\LayoutBlockVariableResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

/**
 * Tests for LayoutBlockVariableResolver.
 *
 * Strategy:
 *   - resolveBlock() and buildVariableMap() are tested independently.
 *   - Models are partial mocks (withPartial) so real property access works.
 *   - No DB calls — Site::find() is tested via the resolveSite path by
 *     injecting a context with a siteId whose Site is mocked at class level.
 */
class LayoutBlockVariableResolverTest extends FunctionalTestCase
{
    private LayoutBlockVariableResolver $resolver;
    private SiteRepository $siteRepository;

    public function test_resolves_simple_variable_in_string(): void
    {
        $data = ['title' => '{{newsletter.title}}'];
        $vars = ['newsletter.title' => 'WWW Curates'];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame('WWW Curates', $result['title']);
    }

    public function test_resolves_variable_embedded_in_longer_string(): void
    {
        $data = ['subtitle' => 'Welcome to {{newsletter.title}} — enjoy'];
        $vars = ['newsletter.title' => 'The Weekly Edit'];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame('Welcome to The Weekly Edit — enjoy', $result['subtitle']);
    }

    // =========================================================================
    // resolveBlock — string interpolation
    // =========================================================================

    public function test_resolves_multiple_variables_in_one_string(): void
    {
        $data = ['line' => '{{site.name}} | {{newsletter.title}}'];
        $vars = [
            'site.name' => 'WhoWhatWear',
            'newsletter.title' => 'The Edit',
        ];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame('WhoWhatWear | The Edit', $result['line']);
    }

    public function test_uses_default_when_variable_not_in_map(): void
    {
        $data = ['color' => '{{newsletter.brand_color|default:#000000}}'];
        $vars = [];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame('#000000', $result['color']);
    }

    public function test_uses_default_when_variable_resolves_to_null(): void
    {
        $data = ['color' => '{{newsletter.brand_color|default:#ffffff}}'];
        $vars = ['newsletter.brand_color' => null];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame('#ffffff', $result['color']);
    }

    public function test_uses_default_when_variable_resolves_to_empty_string(): void
    {
        $data = ['color' => '{{newsletter.brand_color|default:#cccccc}}'];
        $vars = ['newsletter.brand_color' => ''];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame('#cccccc', $result['color']);
    }

    public function test_leaves_placeholder_intact_when_unresolvable_and_no_default(): void
    {
        $data = ['title' => '{{newsletter.missing_field}}'];
        $vars = [];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame('{{newsletter.missing_field}}', $result['title']);
    }

    public function test_resolved_value_takes_precedence_over_default(): void
    {
        $data = ['color' => '{{newsletter.brand_color|default:#000000}}'];
        $vars = ['newsletter.brand_color' => '#ff0000'];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame('#ff0000', $result['color']);
    }

    public function test_non_string_values_are_passed_through_unchanged(): void
    {
        $data = [
            'count' => 42,
            'active' => true,
            'nothing' => null,
        ];

        $result = $this->resolver->resolveBlock($data, []);

        $this->assertSame(42, $result['count']);
        $this->assertSame(true, $result['active']);
        $this->assertNull($result['nothing']);
    }

    public function test_resolves_variables_in_nested_arrays(): void
    {
        $data = [
            'image' => ['src' => '{{newsletter.logo_url|default:https://example.com/logo.png}}'],
        ];
        $vars = ['newsletter.logo_url' => 'https://cdn.example.com/custom-logo.png'];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame(
            'https://cdn.example.com/custom-logo.png',
            $result['image']['src']
        );
    }

    // =========================================================================
    // resolveBlock — type handling
    // =========================================================================

    public function test_resolves_variables_in_deeply_nested_arrays(): void
    {
        $data = [
            'level1' => [
                'level2' => [
                    'value' => '{{site.name}}',
                ],
            ],
        ];
        $vars = ['site.name' => 'Dennis Publishing'];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame('Dennis Publishing', $result['level1']['level2']['value']);
    }

    public function test_handles_empty_block_data(): void
    {
        $result = $this->resolver->resolveBlock([], ['newsletter.title' => 'X']);

        $this->assertSame([], $result);
    }

    public function test_handles_empty_variable_map(): void
    {
        $data = ['title' => 'Static title'];

        $result = $this->resolver->resolveBlock($data, []);

        $this->assertSame('Static title', $result['title']);
    }

    public function test_handles_static_string_with_no_placeholders(): void
    {
        $data = ['backgroundColor' => '#1a1a1a'];

        $result = $this->resolver->resolveBlock($data, ['newsletter.brand_color' => '#ff0000']);

        $this->assertSame('#1a1a1a', $result['backgroundColor']);
    }

    public function test_tolerates_whitespace_inside_placeholder(): void
    {
        $data = ['title' => '{{ newsletter.title }}'];
        $vars = ['newsletter.title' => 'Spaced Out'];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame('Spaced Out', $result['title']);
    }

    public function test_default_value_with_url(): void
    {
        $data = ['ctaUrl' => '{{newsletter.cta_url|default:https://example.com}}'];
        $vars = [];

        $result = $this->resolver->resolveBlock($data, $vars);

        $this->assertSame('https://example.com', $result['ctaUrl']);
    }

    // =========================================================================
    // resolveBlock — whitespace tolerance
    // =========================================================================

    public function test_build_variable_map_exposes_newsletter_title(): void
    {
        $context = $this->makeContext(['title' => 'Fashion Weekly']);

        $this->setSiteExpectations();

        $map = $this->resolver->buildVariableMap($context);

        $this->assertArrayHasKey('newsletter.title', $map);
        $this->assertSame('Fashion Weekly', $map['newsletter.title']);
    }

    /**
     * Build a context with a partial-mock Newsletter and a real (mocked) Site.
     */
    private function makeContext(
        array   $newsletterAttrs = [],
        ?string $siteName = null,
        int     $siteId = 1,
    ): NewsletterRenderContext
    {
        /** @var Newsletter&MockInterface $newsletter */
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();

        $defaults = [
            'id' => 1,
            'title' => 'Default Newsletter',
            'slug' => 'default-newsletter',
            'template' => 'curates',
            'interval' => 'weekly',
            'brand_color' => null,
            'brand_secondary_color' => null,
            'design_config' => [],
        ];

        foreach (array_merge($defaults, $newsletterAttrs) as $attr => $value) {
            $newsletter->{$attr} = $value;
        }

        // Wire Site::find to a mock when a siteName is provided
        if ($siteName !== null) {
            $site = Mockery::mock(Site::class)->makePartial();
            $site->id = $siteId;
            $site->name = $siteName;
            $site->url = 'https://example.com';

            // Bind the mock site via a property so resolveSite() returns it.
            // Because resolveSite() calls Site::find() we alias the Site class
            // in this test — or, simpler, we override the resolver to accept
            // an optional site resolver callable.
            // Instead we use the approach of registering a Mockery alias:
            $this->siteRepository->shouldReceive('find')
                ->with($siteId)
                ->andReturn($site)
                ->byDefault();
        }

        return new NewsletterRenderContext(
            siteId: $siteId,
            newsletter: $newsletter,
            member: null,
            sendId: null,
            includeTracking: false,
        );
    }

    // =========================================================================
    // buildVariableMap — newsletter namespace
    // =========================================================================

    private function setSiteExpectations()
    {
        $this->siteRepository->shouldReceive('find')->andReturn(null);
    }

    public function test_build_variable_map_exposes_newsletter_slug(): void
    {
        $context = $this->makeContext(['slug' => 'fashion-weekly']);

        $this->setSiteExpectations();

        $map = $this->resolver->buildVariableMap($context);

        $this->assertSame('fashion-weekly', $map['newsletter.slug']);
    }

    public function test_build_variable_map_omits_null_newsletter_attributes(): void
    {
        $context = $this->makeContext(['brand_color' => null]);

        $this->setSiteExpectations();

        $map = $this->resolver->buildVariableMap($context);

        $this->assertArrayNotHasKey('newsletter.brand_color', $map);
    }

    public function test_build_variable_map_exposes_newsletter_brand_color(): void
    {
        $context = $this->makeContext(['brand_color' => '#ff5500']);

        $this->setSiteExpectations();

        $map = $this->resolver->buildVariableMap($context);

        $this->assertSame('#ff5500', $map['newsletter.brand_color']);
    }

    public function test_build_variable_map_casts_integer_to_string(): void
    {
        $context = $this->makeContext(['id' => 42]);

        $this->setSiteExpectations();

        $map = $this->resolver->buildVariableMap($context);

        $this->assertSame('42', $map['newsletter.id']);
    }

    public function test_build_variable_map_exposes_design_config_scalars(): void
    {
        $context = $this->makeContext([
            'design_config' => ['primary_color' => '#123456', 'font' => 'Arial'],
        ]);

        $this->setSiteExpectations();

        $map = $this->resolver->buildVariableMap($context);

        $this->assertSame('#123456', $map['newsletter.design_config.primary_color']);
        $this->assertSame('Arial', $map['newsletter.design_config.font']);
    }

    public function test_build_variable_map_skips_non_scalar_design_config_values(): void
    {
        $context = $this->makeContext([
            'design_config' => ['nested' => ['a' => 'b']],
        ]);

        $this->setSiteExpectations();

        $map = $this->resolver->buildVariableMap($context);

        $this->assertArrayNotHasKey('newsletter.design_config.nested', $map);
    }

    // =========================================================================
    // buildVariableMap — site namespace
    // =========================================================================

    public function test_build_variable_map_has_site_namespace_keys(): void
    {
        $map = $this->resolver->buildVariableMap(
            $this->makeContext([], siteName: 'WhoWhatWear')
        );

        $this->assertArrayHasKey('site.name', $map);
        $this->assertSame('WhoWhatWear', $map['site.name']);
    }

    public function test_build_variable_map_tolerates_null_site(): void
    {
        // siteId 0 — Site::find returns null
        $context = $this->makeContextWithSiteId(0, ['title' => 'Test NL']);

        // Should not throw, site.* keys simply absent
        $map = $this->resolver->buildVariableMap($context);

        $this->assertArrayNotHasKey('site.name', $map);
        $this->assertArrayHasKey('newsletter.title', $map);
    }

    // =========================================================================
    // Integration: buildVariableMap → resolveBlock
    // =========================================================================

    private function makeContextWithSiteId(int $siteId, array $newsletterAttrs = []): NewsletterRenderContext
    {
        /** @var Newsletter&MockInterface $newsletter */
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();

        $defaults = [
            'id' => 1,
            'title' => 'Test',
            'slug' => 'test',
            'template' => 'curates',
            'interval' => 'weekly',
            'brand_color' => null,
            'design_config' => [],
        ];

        foreach (array_merge($defaults, $newsletterAttrs) as $attr => $value) {
            $newsletter->{$attr} = $value;
        }

        $this->siteRepository->shouldReceive('find')
            ->with($siteId)
            ->andReturnNull()
            ->byDefault();

        return new NewsletterRenderContext(
            siteId: $siteId,
            newsletter: $newsletter,
            member: null,
            sendId: null,
            includeTracking: false,
        );
    }

    public function test_full_resolve_flow_masthead_banner(): void
    {
        $context = $this->makeContext(
            ['title' => 'WWW Curates — Weekly Edit', 'brand_color' => '#1a1a1a'],
            siteName: 'WhoWhatWear'
        );

        $blockData = [
            'bannerType' => 'masthead',
            'title' => '{{newsletter.title}}',
            'backgroundColor' => '{{newsletter.brand_color|default:#000000}}',
            'textColor' => '#ffffff',
        ];

        $map = $this->resolver->buildVariableMap($context);
        $result = $this->resolver->resolveBlock($blockData, $map);

        $this->assertSame('WWW Curates — Weekly Edit', $result['title']);
        $this->assertSame('#1a1a1a', $result['backgroundColor']);
        $this->assertSame('#ffffff', $result['textColor']);
    }

    public function test_full_resolve_flow_footer_uses_site_name(): void
    {
        $context = $this->makeContext([], siteName: 'Dennis Publishing');

        $blockData = ['title' => '{{site.name}}'];

        $map = $this->resolver->buildVariableMap($context);
        $result = $this->resolver->resolveBlock($blockData, $map);

        $this->assertSame('Dennis Publishing', $result['title']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_full_resolve_flow_default_applies_when_brand_color_absent(): void
    {
        $context = $this->makeContext(['title' => 'Test']);   // no brand_color

        $blockData = ['backgroundColor' => '{{newsletter.brand_color|default:#ff0000}}'];

        $this->setSiteExpectations();

        $map = $this->resolver->buildVariableMap($context);
        $result = $this->resolver->resolveBlock($blockData, $map);

        $this->assertSame('#ff0000', $result['backgroundColor']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->siteRepository = Mockery::mock(SiteRepository::class);
        $this->resolver = new LayoutBlockVariableResolver($this->siteRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}