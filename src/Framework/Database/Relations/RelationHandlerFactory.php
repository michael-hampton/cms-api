<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\Database;
use InvalidArgumentException;

class RelationHandlerFactory
{
    private array $handlers = [];

    public function __construct(private Database $database)
    {
        $this->registerDefaultHandlers();
    }

    private function registerDefaultHandlers(): void
    {
        $this->handlers = [
            'hasMany' => HasManyHandler::class,
            'hasOne' => HasOneHandler::class,
            'belongsTo' => BelongsToHandler::class,
            'belongsToMany' => BelongsToManyHandler::class,
            'morphMany' => MorphManyHandler::class,
            'morphOne' => MorphOneHandler::class,
            'morphTo' => MorphToHandler::class,
        ];
    }

    public function create(string $type, array $relationData, bool $returnHandler = false): RelationshipHandler
    {
        if (!isset($this->handlers[$type])) {
            throw new InvalidArgumentException("Unknown relation type: {$type}");
        }

        $handlerClass = $this->handlers[$type];
        return new $handlerClass($this->database, $relationData, $returnHandler);
    }

    public function registerHandler(string $type, string $handlerClass): void
    {
        if (!is_subclass_of($handlerClass, RelationshipHandler::class)) {
            throw new InvalidArgumentException(
                "Handler must extend AbstractRelationshipHandler"
            );
        }

        $this->handlers[$type] = $handlerClass;
    }
}