<?php
namespace App\Framework\Authorization;

class AuthenticatedMember
{
    public int $id;
    public string $email;
    public string $firstName;
    public string $lastName;
    public ?string $displayName;
    public array $roles;
    public bool $exists = false;

    public function __construct(
        int     $id,
        string  $email,
        string  $firstName,
        string  $lastName,
        ?string $displayName = null,
        array   $roles = []
    )
    {
        $this->id = $id;
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->displayName = $displayName;
        $this->roles = $roles;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getDisplayName(): string
    {
        return $this->displayName ?? $this->getFullName();
    }

    public function hasRole(string $roleSlug): bool
    {
        return in_array($roleSlug, $this->roles);
    }

    public function hasAnyRole(array $roleSlugs): bool
    {
        return !empty(array_intersect($this->roles, $roleSlugs));
    }
}