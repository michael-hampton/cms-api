<?php

namespace App\Tests\Unit\Models;

use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class UserTest extends FunctionalTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = new User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'hashed_password',
            'role' => 'admin',
            'is_active' => true,
            'site_id' => 1
        ]);
    }

    public function testUserCanBeInstantiated()
    {
        $this->assertInstanceOf(User::class, $this->user);
    }

    public function testUserHasCorrectTableName()
    {
        $this->assertEquals('users', $this->user->getTable());
    }
    public function testVerifyPasswordReturnsTrue()
    {
        $result = $this->user->verifyPassword('any_password');
        $this->assertTrue($result);
    }

    public function testIsActiveReturnsTrue()
    {
        $result = $this->user->isActive();
        $this->assertTrue($result);
    }

    public function testCreateUser()
    {
        $user = User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => 'hashed_password_123',
            'role' => 'admin',
            'is_active' => true,
            'site_id' => 1
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Jane Smith', $user->name);
        $this->assertEquals('jane@example.com', $user->email);
        $this->assertEquals('admin', $user->role);
    }

    public function testFillMethodPopulatesAttributes()
    {
        $user = new User();
        $user->fill([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'role' => 'subscriber'
        ]);

        $this->assertEquals('Bob Johnson', $user->name);
        $this->assertEquals('bob@example.com', $user->email);
        $this->assertEquals('subscriber', $user->role);
    }

    public function testSetAndGetName()
    {
        $this->user->name = 'Updated Name';
        $this->assertEquals('Updated Name', $this->user->name);
    }

    public function testSetAndGetEmail()
    {
        $this->user->email = 'newemail@example.com';
        $this->assertEquals('newemail@example.com', $this->user->email);
    }

    public function testSetAndGetRole()
    {
        $this->user->role = 'moderator';
        $this->assertEquals('moderator', $this->user->role);
    }

    public function testSetAndGetIsActive()
    {
        $this->user->is_active = false;
        $this->assertFalse($this->user->is_active);

        $this->user->is_active = true;
        $this->assertTrue($this->user->is_active);
    }

    public function testSetAndGetSiteId()
    {
        $this->user->site_id = 5;
        $this->assertEquals(5, $this->user->site_id);
    }

//    public function testPasswordIsHiddenFromArray()
//    {
//        $array = $this->user->toArray();
//        $this->assertArrayNotHasKey('password', $array);
//    }
//
//    public function testRememberTokenIsHiddenFromArray()
//    {
//        $this->user->remember_token = 'secret_token_123';
//        $array = $this->user->toArray();
//        $this->assertArrayNotHasKey('remember_token', $array);
//    }

    public function testIsActiveCastsToBoolean()
    {
        $this->user->is_active = 1;
        $this->assertIsBool($this->user->is_active);
        $this->assertTrue($this->user->is_active);

        $this->user->is_active = 0;
        $this->assertIsBool($this->user->is_active);
        $this->assertFalse($this->user->is_active);
    }
}