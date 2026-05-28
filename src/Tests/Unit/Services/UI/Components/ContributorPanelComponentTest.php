<?php

namespace App\Tests\Unit\Services\UI\Components;

use App\Framework\View\ViewRenderer;
use App\Services\UI\Components\Contributor\ContributorCapabilitiesPanel;
use App\Services\UI\Components\Contributor\ContributorDetailsPanel;
use App\Services\UI\Components\Contributor\ContributorIndexInvitationPanel;
use App\Services\UI\Components\Contributor\ContributorInvitationPanel;
use Mockery;
use PHPUnit\Framework\TestCase;

class ContributorPanelComponentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_contributor_details_panel_renders_expected_view(): void
    {
        $renderer = Mockery::mock(ViewRenderer::class);
        $renderer->shouldReceive('render')
            ->once()
            ->with('open-collab.admin.contributors.panels.details', ['foo' => 'bar'])
            ->andReturn('<div>details</div>');

        $component = new ContributorDetailsPanel($renderer);

        $this->assertSame('contributor.details', $component->key());
        $this->assertSame('server', $component->mode());
        $this->assertSame([
            'key' => 'contributor.details',
            'mode' => 'server',
            'component' => ContributorDetailsPanel::class,
        ], $component->descriptor());
        $this->assertSame('<div>details</div>', $component->render(['foo' => 'bar']));
    }

    public function test_contributor_invitation_panel_renders_expected_view(): void
    {
        $renderer = Mockery::mock(ViewRenderer::class);
        $renderer->shouldReceive('render')
            ->once()
            ->with('open-collab.admin.contributors.panels.invitation', ['foo' => 'bar'])
            ->andReturn('<div>invitation</div>');

        $component = new ContributorInvitationPanel($renderer);

        $this->assertSame('contributor.invitation', $component->key());
        $this->assertSame('server', $component->mode());
        $this->assertSame('<div>invitation</div>', $component->render(['foo' => 'bar']));
    }

    public function test_contributor_capabilities_panel_renders_expected_view(): void
    {
        $renderer = Mockery::mock(ViewRenderer::class);
        $renderer->shouldReceive('render')
            ->once()
            ->with('open-collab.admin.contributors.panels.capabilities', ['foo' => 'bar'])
            ->andReturn('<div>capabilities</div>');

        $component = new ContributorCapabilitiesPanel($renderer);

        $this->assertSame('contributor.capabilities', $component->key());
        $this->assertSame('server', $component->mode());
        $this->assertSame('<div>capabilities</div>', $component->render(['foo' => 'bar']));
    }

    public function test_contributor_index_invitation_panel_renders_expected_view(): void
    {
        $renderer = Mockery::mock(ViewRenderer::class);
        $renderer->shouldReceive('render')
            ->once()
            ->with('open-collab.admin.contributors.panels.index-invitation', ['foo' => 'bar'])
            ->andReturn('<div>index invitation</div>');

        $component = new ContributorIndexInvitationPanel($renderer);

        $this->assertSame('contributors.invitation', $component->key());
        $this->assertSame('server', $component->mode());
        $this->assertSame('<div>index invitation</div>', $component->render(['foo' => 'bar']));
    }
}
