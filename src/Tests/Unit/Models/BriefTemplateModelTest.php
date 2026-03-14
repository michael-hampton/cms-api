<?php

namespace App\Tests\Unit\Models;

use App\DTO\Briefs\BriefPresetSubtask;
use App\Models\BriefTemplate;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use InvalidArgumentException;

class BriefTemplateModelTest extends FunctionalTestCase
{
    public function test_subtask_requires_title(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BriefPresetSubtask::fromArray(['title' => '']);
    }

    public function test_subtask_requires_title_whitespace_only(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BriefPresetSubtask::fromArray(['title' => '   ']);
    }

    public function test_subtask_requires_title_missing_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BriefPresetSubtask::fromArray([]);
    }

    public function test_subtask_valid_with_title_only(): void
    {
        $subtask = BriefPresetSubtask::fromArray(['title' => 'Write draft']);

        $this->assertSame('Write draft', $subtask->title);
        $this->assertNull($subtask->description);
        $this->assertNull($subtask->defaultOwnerId);
        $this->assertNull($subtask->defaultReviewerId);
    }

    public function test_subtask_to_array_excludes_nulls(): void
    {
        $subtask = BriefPresetSubtask::fromArray(['title' => 'Write draft']);

        $array = $subtask->toArray();

        $this->assertArrayHasKey('title', $array);
        $this->assertArrayNotHasKey('description', $array);
        $this->assertArrayNotHasKey('defaultOwnerId', $array);
        $this->assertArrayNotHasKey('defaultReviewerId', $array);
    }

    public function test_subtask_to_array_includes_all_fields(): void
    {
        $subtask = BriefPresetSubtask::fromArray([
            'title' => 'Write draft',
            'description' => 'First draft of content',
            'defaultOwnerId' => '123',
            'defaultReviewerId' => '456',
        ]);

        $array = $subtask->toArray();

        $this->assertSame('Write draft', $array['title']);
        $this->assertSame('First draft of content', $array['description']);
        $this->assertSame('123', $array['defaultOwnerId']);
        $this->assertSame('456', $array['defaultReviewerId']);
    }

    public function test_subtask_roundtrips_through_array(): void
    {
        $original = BriefPresetSubtask::fromArray([
            'title' => 'Review',
            'defaultOwnerId' => '7',
        ]);

        $restored = BriefPresetSubtask::fromArray($original->toArray());

        $this->assertSame($original->title, $restored->title);
        $this->assertSame($original->defaultOwnerId, $restored->defaultOwnerId);
        $this->assertNull($restored->description);
    }

    public function test_brief_template_default_subtasks_casts_to_array(): void
    {
        $template = new BriefTemplate();

        $raw = [['title' => 'Draft']];
        $template->forceFill(['default_subtasks' => json_encode($raw)]);

        // The 'array' cast should decode the JSON automatically.
        $this->assertIsArray($template->default_subtasks);
        $this->assertSame('Draft', $template->default_subtasks[0]['title']);
    }

    public function test_get_default_subtasks_typed_returns_value_objects(): void
    {
        $template = new BriefTemplate();
        $template->forceFill([
            'default_subtasks' => [
                ['title' => 'Write draft', 'description' => 'First pass'],
                ['title' => 'Review'],
            ],
        ]);

        $typed = $template->getDefaultSubtasksTyped();

        $this->assertCount(2, $typed);
        $this->assertInstanceOf(BriefPresetSubtask::class, $typed[0]);
        $this->assertSame('Write draft', $typed[0]->title);
        $this->assertSame('First pass', $typed[0]->description);
        $this->assertSame('Review', $typed[1]->title);
    }

    public function test_default_owner_ids_casts_to_array(): void
    {
        $template = new BriefTemplate();
        $template->forceFill(['default_owner_ids' => json_encode([1, 2, 3])]);

        $this->assertIsArray($template->default_owner_ids);
        $this->assertSame([1, 2, 3], $template->default_owner_ids);
    }

    public function test_default_subtasks_defaults_to_empty_array_when_null(): void
    {
        $template = new BriefTemplate();

        // No default_subtasks set.
        $typed = $template->getDefaultSubtasksTyped();

        $this->assertIsArray($typed);
        $this->assertEmpty($typed);
    }

    public function test_get_default_subtasks_typed_throws_on_invalid_subtask(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $template = new BriefTemplate();
        $template->forceFill(['default_subtasks' => [['title' => '']]]);

        $template->getDefaultSubtasksTyped();
    }
}