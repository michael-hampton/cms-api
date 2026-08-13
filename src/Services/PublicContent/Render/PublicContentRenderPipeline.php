<?php

namespace App\Services\PublicContent\Render;

/**
 * Ordered pre/post slots around shell build. Carries no locale, link or image
 * logic of its own — only registration and execution order.
 */
final class PublicContentRenderPipeline
{
    /** @var list<PublicContentRenderStep> */
    private array $preSteps = [];

    /** @var list<PublicContentRenderStep> */
    private array $postSteps = [];

    public function registerPre(PublicContentRenderStep $step): void
    {
        $this->preSteps[] = $step;
    }

    public function registerPost(PublicContentRenderStep $step): void
    {
        $this->postSteps[] = $step;
    }

    /**
     * @return list<string> Step names in the order they ran
     */
    public function run(PublicContentRenderContext $context, callable $buildShell): PublicContentRenderContext
    {
        foreach ($this->preSteps as $step) {
            $context = $step->handle($context);
            $context->record($step->name(), 'pre');
        }

        $context = $buildShell($context);
        $context->record('build_shell', 'build');

        foreach ($this->postSteps as $step) {
            $context = $step->handle($context);
            $context->record($step->name(), 'post');
        }

        return $context;
    }

    /** @return list<string> */
    public function preStepNames(): array
    {
        return array_map(static fn(PublicContentRenderStep $step): string => $step->name(), $this->preSteps);
    }

    /** @return list<string> */
    public function postStepNames(): array
    {
        return array_map(static fn(PublicContentRenderStep $step): string => $step->name(), $this->postSteps);
    }
}
