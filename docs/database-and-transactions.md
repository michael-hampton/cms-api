# Database and Transactions

`App\Framework\Database\Database` is an injected dependency. Application code must use the constructor-injected instance through `$this->database`.

A workflow that performs two or more related database writes must use `$this->database->transaction()`. Every related write belongs inside the callback. The callback returns the workflow result, and the service method returns the result of the transaction.

Static Database access is not permitted in services, actions, controllers, handlers or jobs. Repositories do not own transaction boundaries.

```php
final class ExampleService
{
    public function __construct(
        private readonly Database $database,
        private readonly ExampleRepository $examples,
    ) {
    }

    public function create(CreateExampleDTO $data): Example
    {
        return $this->database->transaction(function () use ($data): Example {
            $example = $this->examples->create($data);
            $this->examples->createInitialState($example->id);

            return $example;
        });
    }
}
```

Critical exceptions must escape the callback so the database layer rolls back. Workflow services must not manually begin, commit or roll back transactions.

Unit tests mock the real `Database` class with Mockery, expect `transaction()` once, execute the supplied callback, and assert both the returned result and the relevant repository or event interactions.