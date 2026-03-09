<?php

namespace App\Framework\Http;

use App\Framework\AuthenticatedUser;
use App\Framework\Authorization\Auth;
use App\Framework\Authorization\Gate;
use App\Framework\Database\Database;
use App\Framework\Exceptions\UnauthorizedException;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Support\HasModel;
use App\Framework\Validation\Rules\AcceptedRule;
use App\Framework\Validation\Rules\AfterOrEqualRule;
use App\Framework\Validation\Rules\AfterRule;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\ConfirmedRule;
use App\Framework\Validation\Rules\DateRule;
use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\ExistsRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\IntegerRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\NullableRule;
use App\Framework\Validation\Rules\NumericRule;
use App\Framework\Validation\Rules\RequiredIfRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\RequiredWithoutRule;
use App\Framework\Validation\Rules\RequiredWithRule;
use App\Framework\Validation\Rules\SometimesRule;
use App\Framework\Validation\Rules\StringRule;
use App\Framework\Validation\Rules\UniqueRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Framework\Validation\ValidationResult;
use App\Framework\Validation\ValidationRuleInterface;
use App\Framework\Validation\Validator;
use App\Models\User;
use Exception;

abstract class FormRequest extends Request
{
    use HasModel;

    protected array $data = [];
    protected array $validatedData = [];
    protected array $errors = [];
    public AuthenticatedUser|User|null $user = null;
    private bool $validated = false;
    protected array $afterCallbacks = [];
    public Validator $validator;

    public function __construct(array $data = [], array $files = [], array $routeParams = [])
    {
        $this->validator = new Validator($this->database ?? Database::getInstance());
        parent::__construct($data, $files, $routeParams);
    }

    /**
     * Create FormRequest from existing Request
     */
    public static function createFromRequest(Request $request): static
    {
        $formRequest = new static(
            $request->all(),
            $request->files(),
            $request->getRouteParams() ?? []
        );

        $formRequest->setUser($request->user());

        return $formRequest;
    }

    /**
     * Validate the request (can be called manually by controller)
     */
    public function validateRequest(): void
    {

        if ($this->validated) {
            return; // Already validated
        }

        // Prepare data before validation
        $this->prepareForValidation();

        // Run authorization check
        if (!$this->authorize()) {
            throw new UnauthorizedException('This action is unauthorized.');
        }

        // Run validation
        $this->performValidation();
        $this->validated = true;
    }

    /**
     * Check if the request passes validation without throwing exceptions
     * Controllers can use this to check validation status
     */
    public function passes(): bool
    {
        try {
            $this->validateRequest();
            return true;
        } catch (ValidationException|UnauthorizedException $e) {
            return false;
        }
    }

    /**
     * Check if the request fails validation
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Get validation errors without throwing exceptions
     */
    public function getValidationErrors(): array
    {
        try {
            $this->validateRequest();
            return [];
        } catch (ValidationException $e) {
            return $e->getErrors();
        } catch (UnauthorizedException $e) {
            return ['authorization' => [$e->getMessage()]];
        }
    }

    /**
     * Validate and return errors in a controller-friendly format
     */
    public function validateWithErrors(): array
    {
        $errors = $this->getValidationErrors();
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => empty($errors) ? $this->validatedData : []
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     */
    abstract public function rules(): array;

    /**
     * Get the policy class for authorization
     */
    protected function getPolicyClass(): ?string
    {
        return null; // Override in child classes
    }

    protected function getModelId(): mixed
    {
        return $this->input('id');
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // If we have a model class only, allow create checks
        $modelClass = $this->getModelClass();

        if (empty($modelClass)) {
            return true;
        }

        $user = $this->user();

        if (!$user) {
            return false;
        }

        $ability = $this->getAbility();
        $model = $this->getModelInstance();

        // If we have a model instance, authorize against it
        if ($model) {
            return Gate::forUser($user, $ability, $model);
        }

        if ($modelClass) {
            return Gate::forUser($user, $ability, $modelClass);
        }

        // Old requests: no model awareness, allow
        return true;
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Override in child classes to modify data before validation
    }

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return $this->afterCallbacks;
    }

    /**
     * Register a custom validator callback
     */
    public function withValidator(callable $callback): void
    {
        $this->afterCallbacks[] = function ($request) use ($callback) {
            $this->validator->validate($this->all(), $this->convertPipeRulesToValidationRules($this->rules()));

            $callback($this->validator);
        };
    }

    /**
     * Perform the actual validation
     */
    protected function performValidation(): void
    {
        $rules = $this->convertPipeRulesToValidationRules($this->rules());
        $data = $this->all();

        $result = $this->validator->validate($data, $rules);

        if (!$result->isValid()) {
            throw new ValidationException('Validation failed', $result->getErrors());
        }

        // Include all input fields except the ones that failed validation
        $failed = $result->getFailedFields();
        $validated = array_diff_key($data, array_flip($failed));

        // Assign all valid data (including non-rule fields)
        $this->validatedData = $validated;

        // Run after validation callbacks
        foreach ($this->after() as $callback) {
            if (is_callable($callback)) {
                $callback($this);
            }
        }
    }

    private function convertPipeRulesToValidationRules(array $formRules): array
    {
        $validationRules = [];

        foreach ($formRules as $field => $rules) {
            $fieldRules = [];

            // Handle both string and array formats
            if (is_string($rules)) {
                // Pipe format: 'required|string|max:255'
                $rulesList = explode('|', $rules);
            } elseif (is_array($rules)) {
                // Array format: ['required', 'string', 'max:255']
                $rulesList = $rules;
            } else {
                continue; // Skip invalid rule formats
            }

            foreach ($rulesList as $rule) {
                $fieldRules[] = $this->createValidationRuleFromString($rule);
            }

            $validationRules[$field] = $fieldRules;
        }

        return $validationRules;
    }

    private function createValidationRuleFromString(string $rule): ValidationRuleInterface
    {
        if (strpos($rule, ':') !== false) {
            [$ruleName, $parameters] = explode(':', $rule, 2);

            // Handle special parameter formats
            if ($ruleName === 'exists') {
                // Format: exists:table,column
                $params = explode(',', $parameters);
            } elseif ($ruleName === 'unique') {
                $params = explode(',', $parameters);
            } else {
                $params = explode(',', $parameters);
            }
        } else {
            $ruleName = $rule;
            $params = [];
        }

        $ruleClass = $this->getRuleClass($ruleName);

        // Create rule instance without database in constructor
        $ruleInstance = new $ruleClass();

        // Set database for rules that need it
        if (method_exists($ruleInstance, 'setDatabase')) {
            $ruleInstance->setDatabase($this->database ?? Database::getInstance());
        }

        if (!empty($params)) {
            $ruleInstance->setParameters($params);
        }

        return $ruleInstance;
    }

    private function getRuleClass(string $ruleName): string
    {
        $ruleMap = [
            'required' => RequiredRule::class,
            'required_if' => RequiredIfRule::class,
            'required_with' => RequiredWithRule::class,
            'required_without' => RequiredWithoutRule::class,
            'date' => DateRule::class,
            'string' => StringRule::class,
            'integer' => IntegerRule::class,
            'boolean' => BooleanRule::class,
            'max' => MaxLengthRule::class,
            'accepted' => AcceptedRule::class,
            'nullable' => NullableRule::class,
            'min' => MinLengthRule::class,
            'email' => EmailRule::class,
            'exists' => ExistsRule::class,
            'unique' => UniqueRule::class,
            'in' => InRule::class,
            'url' => UrlRule::class,
            'array' => ArrayRule::class,
            'numeric' => NumericRule::class,
            'confirmed' => ConfirmedRule::class,
            'sometimes' => SometimesRule::class,
            'after' => AfterRule::class,
            'after_or_equal' => AfterOrEqualRule::class,
            'min_number' => MinRule::class,
        ];

        if (!isset($ruleMap[$ruleName])) {
            throw new Exception("Unknown validation rule: {$ruleName}");
        }

        return $ruleMap[$ruleName];
    }


    /**
     * Validate the request data
     */
    protected function validate(): void
    {
        $rules = $this->rules();
        $messages = $this->messages();

        foreach ($rules as $field => $rule) {
            $value = $this->input($field);
            $fieldRules = $this->parseRules($rule);

            foreach ($fieldRules as $ruleName => $ruleValue) {
                if (!$this->validateField($field, $value, $ruleName, $ruleValue)) {
                    $this->addError($field, $this->getErrorMessage($field, $ruleName, $ruleValue, $messages));
                }
            }
        }

        if (!empty($this->errors)) {
            $result = new ValidationResult(false, $this->errors);
            throw new ValidationException($result);
        }

        // Store validated data (only fields that have rules)
        foreach (array_keys($rules) as $field) {
            if ($this->has($field)) {
                $this->validatedData[$field] = $this->input($field);
            }
        }

        // Run after validation callbacks
        foreach ($this->after() as $callback) {
            if (is_callable($callback)) {
                $callback($this);
            }
        }
    }

    /**
     * Parse validation rules from string or array format
     */
    protected function parseRules($rules): array
    {
        if (is_string($rules)) {
            // Handle pipe format: "required|string|max:255"
            $ruleArray = [];
            $parts = explode('|', $rules);

            foreach ($parts as $part) {
                if (strpos($part, ':') !== false) {
                    [$ruleName, $ruleValue] = explode(':', $part, 2);
                    $ruleArray[$ruleName] = $ruleValue;
                } else {
                    $ruleArray[$part] = true;
                }
            }

            return $ruleArray;
        }

        if (is_array($rules)) {
            // Handle array format: ["required", "string", "max:255"]
            $ruleArray = [];

            foreach ($rules as $rule) {
                if (is_string($rule)) {
                    if (strpos($rule, ':') !== false) {
                        [$ruleName, $ruleValue] = explode(':', $rule, 2);
                        $ruleArray[$ruleName] = $ruleValue;
                    } else {
                        $ruleArray[$rule] = true;
                    }
                }
            }

            return $ruleArray;
        }

        return [];
    }

    /**
     * Validate a single field against a rule
     */
    protected function validateField(string $field, $value, string $ruleName, $ruleValue): bool
    {
        if (empty($value) && $ruleName !== 'required') {
            return true;
        }

        switch ($ruleName) {
            case 'required':
                return !empty($value) || $value === '0' || $value === 0;

            case 'string':
                return is_string($value);

            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;

            case 'numeric':
                return is_numeric($value);

            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            case 'min':
                if (is_string($value)) {
                    return strlen($value) >= (int)$ruleValue;
                }
                if (is_numeric($value)) {
                    return $value >= (float)$ruleValue;
                }
                return false;

            case 'max':
                if (is_string($value)) {
                    return strlen($value) <= (int)$ruleValue;
                }
                if (is_numeric($value)) {
                    return $value <= (float)$ruleValue;
                }
                return false;

            case 'in':
                $allowedValues = explode(',', $ruleValue);
                return in_array($value, $allowedValues);

            case 'exists':
                // Simple exists validation - in real implementation would check database
                return !empty($value);

            case 'unique':
                // Simple unique validation - in real implementation would check database
                return true;

            default:
                return true;
        }
    }

    /**
     * Get error message for validation rule
     */
    protected function getErrorMessage(string $field, string $rule, $ruleValue, array $messages): string
    {
        $key = "{$field}.{$rule}";

        if (isset($messages[$key])) {
            return $messages[$key];
        }

        // Default error messages
        $defaultMessages = [
            'required' => "The {$field} field is required.",
            'string' => "The {$field} must be a string.",
            'integer' => "The {$field} must be an integer.",
            'numeric' => "The {$field} must be a number.",
            'email' => "The {$field} must be a valid email address.",
            'min' => "The {$field} must be at least {$ruleValue} characters.",
            'max' => "The {$field} may not be greater than {$ruleValue} characters.",
            'in' => "The selected {$field} is invalid.",
            'exists' => "The selected {$field} is invalid.",
            'unique' => "The {$field} has already been taken.",
        ];

        return $defaultMessages[$rule] ?? "The {$field} field is invalid.";
    }

    /**
     * Add an error to the errors array
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Get all validated input data
     */
    public function validated(): array
    {
        if (!$this->validated) {
            $this->validateRequest();
        }
        return $this->validatedData;
    }

    /**
     * Get the safe input data
     */
    public function safe(): SafeInput
    {
        return new SafeInput($this->validatedData);
    }

    /**
     * Get input value
     */
    public function input(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if input has a key
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Override parent methods to trigger validation if needed
     */
    public function all(): array
    {
        // Still return all input, not just validated
        return parent::all();
    }

    /**
     * Get the current user
     */
    public function user(): ?AuthenticatedUser
    {
        return Auth::loadUserFromToken();
    }

    /**
     * Get validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }
}