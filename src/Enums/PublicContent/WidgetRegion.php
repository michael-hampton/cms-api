<?php

namespace App\Enums\PublicContent;

/**
 * Widget layout slots. Config/editor values include aliases (top, middle, bottom)
 * that resolve to the canonical composition slots used by the public API.
 */
enum WidgetRegion: string
{
    case Notices = 'notices';
    case Header = 'header';
    case Top = 'top';
    case Middle = 'middle';
    case AfterContent = 'after-content';
    case BelowContent = 'below-content';
    case Bottom = 'bottom';
    case Sidebar = 'sidebar';
    case Modals = 'modals';

    public static function tryFromConfig(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }

    public static function fromConfig(mixed $value): self
    {
        $region = self::tryFromConfig($value);

        if ($region === null) {
            throw new \InvalidArgumentException('Unknown widget region.');
        }

        return $region;
    }

    /**
     * Canonical slot used in composed public-content documents.
     * Aliases keep config-editor wording without changing the API contract.
     */
    public function layoutSlot(): self
    {
        return match ($this) {
            self::Top => self::Header,
            self::Middle => self::AfterContent,
            self::Bottom => self::BelowContent,
            default => $this,
        };
    }

    /**
     * Value shown in the config editor for a stored/canonical region.
     */
    public function editorChoice(): self
    {
        return match ($this) {
            self::Header, self::Top => self::Top,
            self::AfterContent, self::Middle => self::Middle,
            self::BelowContent, self::Bottom => self::Bottom,
            default => $this,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Notices => 'Notices',
            self::Header => 'Header',
            self::Top => 'Top',
            self::Middle => 'Middle',
            self::AfterContent => 'After content',
            self::BelowContent => 'Below content',
            self::Bottom => 'Bottom',
            self::Sidebar => 'Sidebar',
            self::Modals => 'Modals',
        };
    }

    /**
     * Options shown in the public-content config editor.
     *
     * @return list<array{value: string, label: string, aliases: list<string>}>
     */
    public static function configEditorOptions(): array
    {
        $choices = [
            self::Top,
            self::Middle,
            self::Bottom,
            self::Sidebar,
            self::Notices,
            self::Modals,
        ];

        return array_map(
            static function (self $region): array {
                $aliases = [$region->value];
                foreach (self::cases() as $candidate) {
                    if ($candidate->editorChoice() === $region && $candidate !== $region) {
                        $aliases[] = $candidate->value;
                    }
                }

                return [
                    'value' => $region->value,
                    'label' => $region->label(),
                    'aliases' => $aliases,
                ];
            },
            $choices,
        );
    }
}
