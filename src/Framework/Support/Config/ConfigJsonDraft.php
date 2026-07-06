<?php

namespace App\Framework\Support\Config;

use App\Framework\Support\Config\Json\DuplicateKeyAwareJsonParser;
use App\Framework\Support\Config\Json\JsonSyntaxException;

/**
 * Represents one attempt to turn raw JSON text (as typed into the raw
 * JSON editor) into a ConfigModel.
 *
 * This is the backend's authoritative version of the same two-step rule
 * the frontend draft editor follows: parsing and validation are always
 * kept as separate steps, so a caller can distinguish:
 *   - syntactically invalid JSON (isValidSyntax === false), vs.
 *   - syntactically valid JSON that fails configuration rules
 *     (isValidSyntax === true, validationErrors !== []).
 *
 * The raw text is always preserved on the instance, whether or not it
 * parsed — callers must never discard what the user typed.
 */
final class ConfigJsonDraft
{
    private function __construct(
        public readonly string $rawText,
        public readonly bool $isValidSyntax,
        public readonly ?string $syntaxError,
        public readonly ?ConfigModel $model,
        public readonly array $validationErrors,
    ) {
    }

    public static function fromJsonText(string $rawText, ?ConfigValidator $validator = null): self
    {
        $validator ??= new ConfigValidator();

        try {
            $pairs = DuplicateKeyAwareJsonParser::parseObjectPairs($rawText);
        } catch (JsonSyntaxException $e) {
            return new self(
                rawText: $rawText,
                isValidSyntax: false,
                syntaxError: $e->getMessage(),
                model: null,
                validationErrors: [],
            );
        }

        $model = ConfigModel::fromPairs($pairs);

        return new self(
            rawText: $rawText,
            isValidSyntax: true,
            syntaxError: null,
            model: $model,
            validationErrors: $validator->validate($model),
        );
    }

    public function isValidConfiguration(): bool
    {
        return $this->isValidSyntax && $this->validationErrors === [];
    }

    /**
     * @return array{
     *   isValidSyntax: bool,
     *   syntaxError: ?string,
     *   isValidConfiguration: bool,
     *   validationErrors: list<array{entryId: string, key: string, code: string, message: string}>,
     * }
     */
    public function toArray(): array
    {
        return [
            'isValidSyntax' => $this->isValidSyntax,
            'syntaxError' => $this->syntaxError,
            'isValidConfiguration' => $this->isValidConfiguration(),
            'validationErrors' => array_map(
                static fn (ConfigValidationError $e): array => $e->toArray(),
                $this->validationErrors,
            ),
        ];
    }
}