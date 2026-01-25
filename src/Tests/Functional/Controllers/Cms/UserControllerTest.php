<?php

namespace App\Tests\Functional\Controllers\Cms;

use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class UserControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsPaginatedUsers(): void
    {
        // Create test users
        for ($i = 1; $i <= 20; $i++) {
            $this->createUser();
        }

        $response = $this->getForSite('/api/users');

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('pagination', $data);
    }

    public function testIndexWithSearchQuery(): void
    {
        $this->createUser(['name' => 'John Doe']);
        $this->createUser(['name' => 'Jane Doe']);

        $response = $this->getForSite('/api/users?q=John');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('John Doe', $data['items'][0]['name']);
    }

    public function testIndexWithRoleFilter(): void
    {
        $this->createUser(['role' => 'admin']);
        $this->createUser(['role' => 'user']);

        $response = $this->getForSite('/api/users?role=admin');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['items']); //includes logged in user in auth header
        $this->assertEquals('admin', $data['items'][0]['role']);
    }

    public function testIndexWithIsActiveFilter(): void
    {
        $this->createUser(['is_active' => true]);
        $this->createUser(['is_active' => false]);

        $response = $this->getForSite('/api/users?is_active=true');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        foreach ($data['items'] as $user) {
            $this->assertTrue((bool)$user['is_active']);
        }
    }

    public function testIndexWithSorting(): void
    {
        $this->createUser(['name' => 'Zoe']);
        $this->createUser(['name' => 'Alice']);

        $response = $this->getForSite('/api/users?sort_by=name&sort_order=asc');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Alice', $data['items'][0]['name']);
    }

    public function testIndexWithPagination(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->createUser();
        }

        $response = $this->getForSite('/api/users?page=2&per_page=10');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(2, $data['pagination']['current_page']);
        $this->assertEquals(10, $data['pagination']['per_page']);
        $this->assertCount(10, $data['items']);
    }


    public function testShowReturnsSingleUser(): void
    {
        $user = $this->createUser(['name' => 'John Doe', 'email' => 'john@example.com']);

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

}