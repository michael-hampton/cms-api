<?php

namespace App\Tests\Unit\Authorization;

use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\TestCase;

class MemberModelTest extends FunctionalTestCase
{
    public function testVerifyPasswordWithCorrectPassword()
    {
        $member = new Member([
            'password' => password_hash('testpassword', PASSWORD_DEFAULT)
        ]);

        $this->assertTrue($member->verifyPassword('testpassword'));
    }

    public function testVerifyPasswordWithIncorrectPassword()
    {
        $member = new Member([
            'password' => password_hash('testpassword', PASSWORD_DEFAULT)
        ]);

        $this->assertFalse($member->verifyPassword('wrongpassword'));
    }

    public function testGetFullNameAttribute()
    {
        $member = new Member([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);

        $this->assertEquals('John Doe', $member->full_name);
    }

    public function testIsEmailVerifiedReturnsTrueWhenVerified()
    {
        $member = new Member([
            'email_verified_at' => date('Y-m-d H:i:s')
        ]);

        $this->assertTrue($member->isEmailVerified());
    }

    public function testIsEmailVerifiedReturnsFalseWhenNotVerified()
    {
        $member = new Member([
            'email_verified_at' => null
        ]);

        $this->assertFalse($member->isEmailVerified());
    }
}