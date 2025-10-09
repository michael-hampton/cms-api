<?php

namespace App\Tests\Functional\Controllers;

use App\Models\User;

class UserControllerTest extends FunctionalTestCase
{
    public function testIndexReturnsPaginatedUsers(): void
    {
        // Create test users
        for ($i = 1; $i <= 20; $i++) {
            User::create([
                'name' => "Test User $i",
                'email' => "user$i@example.com",
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'role' => 'user',
                'is_active' => true,
                'site_id' => $this->siteId,
            ]);
        }

        $response = $this->getForSite('/api/users');

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(15, $data['data']); // Default per_page is 15
        $this->assertEquals(1, $data['pagination']['current_page']);
        $this->assertEquals(15, $data['pagination']['per_page']);
    }

    public function testShowReturnsSingleUser(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'user',
            'is_active' => true,
            'site_id' => $this->siteId,
        ]);

        $response = $this->getForSite("/api/users/{$user->id}");

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals($user->id, $data['user']['id']);
        $this->assertEquals('John Doe', $data['user']['name']);
        $this->assertEquals('john@example.com', $data['user']['email']);
    }

    public function testShowReturns404WhenUserNotFound(): void
    {
        $response = $this->getForSite('/api/users/99999');

        $this->assertResponseStatus(404, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('User not found', $data['message']);
    }

    public function testStoreCreatesNewUser(): void
    {
        $email = sprintf('user_%s@example.com', uniqid());

        $data = [
            'name' => 'Jane Doe',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'user',
            'is_active' => true,
            'site_id' => $this->siteId,
        ];

        $response = $this->postForSite('/api/users', $data, []);

        $this->assertResponseStatus(201, $response);
        $this->assertJsonResponse($response);

        $responseData = json_decode($response->getContent(), true);


        $this->assertEquals('Jane Doe', $responseData['data']['name']);
        $this->assertEquals($email, $responseData['data']['email']);
        $this->assertEquals('user', $responseData['data']['role']);
    }

    public function testStoreValidatesRequiredFields(): void
    {
        $response = $this->postForSite('/api/users', []);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreValidatesUniqueEmail(): void
    {
        $user = $this->createUser();

        $data = [
            'name' => 'Jane Doe',
            'email' => $user->email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'user',
        ];

        $response = $this->postForSite('/api/users', $data);

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateModifiesExistingUser(): void
    {
        $user = $this->createUser();
        $email = sprintf('user_%s@example.com', uniqid());

        $data = [
            'name' => 'Updated Name',
            'email' => $email,
        ];

        $response = $this->putForSite("/api/users/{$user->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'name' => 'Updated Name',
                'email' => $email,
            ]);

      $user = User::where('id', $user->id)->where('email', $email)->first();
      $this->assertNotEmpty($user);;
    }

    public function testUpdateReturns404WhenUserNotFound(): void
    {
        $response = $this->putForSite('/api/users/99999', ['name' => 'Test']);

        $response->assertStatus(404)
            ->assertJson(['message' => 'User not found']);
    }

    public function testUpdateValidatesEmailUniqueness(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        // Try to update user1 with user2's email
        $response = $this->putForSite("/api/users/{$user1->id}", [
            'email' => $user2->email,
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function testDestroyDeletesUser(): void
    {
        $user = $this->createUser();

        $response = $this->deleteForSite("/api/users/{$user->id}");

        $response->assertStatus(204);
    }

    public function testDestroyReturns404WhenUserNotFound(): void
    {
        $response = $this->deleteForSite('/api/users/99999');

        $response->assertStatus(404)
            ->assertJson(['message' => 'User not found']);
    }

    private function createUser(array $data = []) {
        $email = !empty($data['email']) ? $data['email'] : sprintf('user_%s@example.com', uniqid());

        $data = [
            'name' => 'Jane Doe',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'user',
            'is_active' => true,
            'site_id' => $this->siteId,
        ];

       return User::create($data);
    }

}