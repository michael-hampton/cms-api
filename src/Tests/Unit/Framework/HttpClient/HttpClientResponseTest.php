<?php

namespace App\Tests\Unit\Framework\HttpClient;

use App\Framework\HttpClient\HttpClientResponse;
use App\Framework\Support\Collection;
use PHPUnit\Framework\TestCase;

class HttpClientResponseTest extends TestCase
{
    public function testStatusReturnsCorrectCode()
    {
        $response = new HttpClientResponse(200, [], 'body');
        $this->assertEquals(200, $response->status());
    }

    public function testGetStatusCodeReturnsCorrectCode()
    {
        $response = new HttpClientResponse(200, [], 'body');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBodyReturnsBody()
    {
        $response = new HttpClientResponse(200, [], 'test body');
        $this->assertEquals('test body', $response->body());
    }

    public function testGetBodyReturnsBody()
    {
        $response = new HttpClientResponse(200, [], 'test body');
        $this->assertEquals('test body', $response->getBody());
    }

    public function testHeadersReturnsHeaders()
    {
        $headers = ['Content-Type' => 'application/json'];
        $response = new HttpClientResponse(200, $headers, '');
        $this->assertEquals($headers, $response->headers());
    }

    public function testGetHeadersReturnsHeaders()
    {
        $headers = ['Content-Type' => 'application/json'];
        $response = new HttpClientResponse(200, $headers, '');
        $this->assertEquals($headers, $response->getHeaders());
    }

    public function testHeaderReturnsSpecificHeader()
    {
        $headers = ['Content-Type' => 'application/json'];
        $response = new HttpClientResponse(200, $headers, '');
        $this->assertEquals('application/json', $response->header('Content-Type'));
    }

    public function testGetHeaderReturnsSpecificHeader()
    {
        $headers = ['Content-Type' => 'application/json'];
        $response = new HttpClientResponse(200, $headers, '');
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
    }

    public function testHeaderReturnsNullForMissingHeader()
    {
        $response = new HttpClientResponse(200, [], '');
        $this->assertNull($response->header('Missing-Header'));
    }

    public function testSuccessfulReturnsTrueFor2xxCodes()
    {
        $this->assertTrue((new HttpClientResponse(200))->successful());
        $this->assertTrue((new HttpClientResponse(201))->successful());
        $this->assertTrue((new HttpClientResponse(204))->successful());
        $this->assertTrue((new HttpClientResponse(299))->successful());
    }

    public function testSuccessfulReturnsFalseForNon2xxCodes()
    {
        $this->assertFalse((new HttpClientResponse(199))->successful());
        $this->assertFalse((new HttpClientResponse(300))->successful());
        $this->assertFalse((new HttpClientResponse(404))->successful());
        $this->assertFalse((new HttpClientResponse(500))->successful());
    }

    public function testIsSuccessfulReturnsTrueFor2xxCodes()
    {
        $this->assertTrue((new HttpClientResponse(200))->isSuccessful());
        $this->assertTrue((new HttpClientResponse(201))->isSuccessful());
    }

    public function testRedirectReturnsTrueFor3xxCodes()
    {
        $this->assertTrue((new HttpClientResponse(300))->redirect());
        $this->assertTrue((new HttpClientResponse(301))->redirect());
        $this->assertTrue((new HttpClientResponse(302))->redirect());
        $this->assertTrue((new HttpClientResponse(399))->redirect());
    }

    public function testRedirectReturnsFalseForNon3xxCodes()
    {
        $this->assertFalse((new HttpClientResponse(200))->redirect());
        $this->assertFalse((new HttpClientResponse(400))->redirect());
        $this->assertFalse((new HttpClientResponse(500))->redirect());
    }

    public function testIsRedirectReturnsTrueFor3xxCodes()
    {
        $this->assertTrue((new HttpClientResponse(301))->isRedirect());
    }

    public function testFailedReturnsTrueFor4xxAnd5xxCodes()
    {
        $this->assertTrue((new HttpClientResponse(400))->failed());
        $this->assertTrue((new HttpClientResponse(404))->failed());
        $this->assertTrue((new HttpClientResponse(500))->failed());
        $this->assertTrue((new HttpClientResponse(503))->failed());
    }

    public function testFailedReturnsFalseFor2xxAnd3xxCodes()
    {
        $this->assertFalse((new HttpClientResponse(200))->failed());
        $this->assertFalse((new HttpClientResponse(301))->failed());
    }

    public function testClientErrorReturnsTrueFor4xxCodes()
    {
        $this->assertTrue((new HttpClientResponse(400))->clientError());
        $this->assertTrue((new HttpClientResponse(404))->clientError());
        $this->assertTrue((new HttpClientResponse(422))->clientError());
        $this->assertTrue((new HttpClientResponse(499))->clientError());
    }

    public function testClientErrorReturnsFalseForNon4xxCodes()
    {
        $this->assertFalse((new HttpClientResponse(200))->clientError());
        $this->assertFalse((new HttpClientResponse(500))->clientError());
    }

    public function testIsClientErrorReturnsTrueFor4xxCodes()
    {
        $this->assertTrue((new HttpClientResponse(404))->isClientError());
    }

    public function testServerErrorReturnsTrueFor5xxCodes()
    {
        $this->assertTrue((new HttpClientResponse(500))->serverError());
        $this->assertTrue((new HttpClientResponse(502))->serverError());
        $this->assertTrue((new HttpClientResponse(503))->serverError());
        $this->assertTrue((new HttpClientResponse(599))->serverError());
    }

    public function testServerErrorReturnsFalseForNon5xxCodes()
    {
        $this->assertFalse((new HttpClientResponse(200))->serverError());
        $this->assertFalse((new HttpClientResponse(404))->serverError());
    }

    public function testIsServerErrorReturnsTrueFor5xxCodes()
    {
        $this->assertTrue((new HttpClientResponse(500))->isServerError());
    }

    public function testOkReturnsTrueFor200()
    {
        $this->assertTrue((new HttpClientResponse(200))->ok());
    }

    public function testOkReturnsFalseForNon200()
    {
        $this->assertFalse((new HttpClientResponse(201))->ok());
        $this->assertFalse((new HttpClientResponse(404))->ok());
    }

    public function testCreatedReturnsTrueFor201()
    {
        $this->assertTrue((new HttpClientResponse(201))->created());
    }

    public function testCreatedReturnsFalseForNon201()
    {
        $this->assertFalse((new HttpClientResponse(200))->created());
        $this->assertFalse((new HttpClientResponse(202))->created());
    }

    public function testAcceptedReturnsTrueFor202()
    {
        $this->assertTrue((new HttpClientResponse(202))->accepted());
    }

    public function testAcceptedReturnsFalseForNon202()
    {
        $this->assertFalse((new HttpClientResponse(200))->accepted());
        $this->assertFalse((new HttpClientResponse(201))->accepted());
    }

    public function testNoContentReturnsTrueFor204()
    {
        $this->assertTrue((new HttpClientResponse(204))->noContent());
    }

    public function testNoContentReturnsFalseForNon204()
    {
        $this->assertFalse((new HttpClientResponse(200))->noContent());
        $this->assertFalse((new HttpClientResponse(404))->noContent());
    }

    public function testMovedPermanentlyReturnsTrueFor301()
    {
        $this->assertTrue((new HttpClientResponse(301))->movedPermanently());
    }

    public function testMovedPermanentlyReturnsFalseForNon301()
    {
        $this->assertFalse((new HttpClientResponse(302))->movedPermanently());
        $this->assertFalse((new HttpClientResponse(200))->movedPermanently());
    }

    public function testFoundReturnsTrueFor302()
    {
        $this->assertTrue((new HttpClientResponse(302))->found());
    }

    public function testFoundReturnsFalseForNon302()
    {
        $this->assertFalse((new HttpClientResponse(301))->found());
        $this->assertFalse((new HttpClientResponse(200))->found());
    }

    public function testBadRequestReturnsTrueFor400()
    {
        $this->assertTrue((new HttpClientResponse(400))->badRequest());
    }

    public function testBadRequestReturnsFalseForNon400()
    {
        $this->assertFalse((new HttpClientResponse(404))->badRequest());
        $this->assertFalse((new HttpClientResponse(200))->badRequest());
    }

    public function testUnauthorizedReturnsTrueFor401()
    {
        $this->assertTrue((new HttpClientResponse(401))->unauthorized());
    }

    public function testUnauthorizedReturnsFalseForNon401()
    {
        $this->assertFalse((new HttpClientResponse(403))->unauthorized());
        $this->assertFalse((new HttpClientResponse(200))->unauthorized());
    }

    public function testPaymentRequiredReturnsTrueFor402()
    {
        $this->assertTrue((new HttpClientResponse(402))->paymentRequired());
    }

    public function testPaymentRequiredReturnsFalseForNon402()
    {
        $this->assertFalse((new HttpClientResponse(401))->paymentRequired());
        $this->assertFalse((new HttpClientResponse(200))->paymentRequired());
    }

    public function testForbiddenReturnsTrueFor403()
    {
        $this->assertTrue((new HttpClientResponse(403))->forbidden());
    }

    public function testForbiddenReturnsFalseForNon403()
    {
        $this->assertFalse((new HttpClientResponse(401))->forbidden());
        $this->assertFalse((new HttpClientResponse(200))->forbidden());
    }

    public function testNotFoundReturnsTrueFor404()
    {
        $this->assertTrue((new HttpClientResponse(404))->notFound());
    }

    public function testNotFoundReturnsFalseForNon404()
    {
        $this->assertFalse((new HttpClientResponse(403))->notFound());
        $this->assertFalse((new HttpClientResponse(200))->notFound());
    }

    public function testRequestTimeoutReturnsTrueFor408()
    {
        $this->assertTrue((new HttpClientResponse(408))->requestTimeout());
    }

    public function testRequestTimeoutReturnsFalseForNon408()
    {
        $this->assertFalse((new HttpClientResponse(404))->requestTimeout());
        $this->assertFalse((new HttpClientResponse(200))->requestTimeout());
    }

    public function testConflictReturnsTrueFor409()
    {
        $this->assertTrue((new HttpClientResponse(409))->conflict());
    }

    public function testConflictReturnsFalseForNon409()
    {
        $this->assertFalse((new HttpClientResponse(404))->conflict());
        $this->assertFalse((new HttpClientResponse(200))->conflict());
    }

    public function testUnprocessableEntityReturnsTrueFor422()
    {
        $this->assertTrue((new HttpClientResponse(422))->unprocessableEntity());
    }

    public function testUnprocessableEntityReturnsFalseForNon422()
    {
        $this->assertFalse((new HttpClientResponse(404))->unprocessableEntity());
        $this->assertFalse((new HttpClientResponse(200))->unprocessableEntity());
    }

    public function testTooManyRequestsReturnsTrueFor429()
    {
        $this->assertTrue((new HttpClientResponse(429))->tooManyRequests());
    }

    public function testTooManyRequestsReturnsFalseForNon429()
    {
        $this->assertFalse((new HttpClientResponse(404))->tooManyRequests());
        $this->assertFalse((new HttpClientResponse(200))->tooManyRequests());
    }

    public function testJsonDecodesJsonBody()
    {
        $body = json_encode(['key' => 'value']);
        $response = new HttpClientResponse(200, [], $body);
        $this->assertEquals(['key' => 'value'], $response->json());
    }

    public function testJsonReturnsNullForInvalidJson()
    {
        $response = new HttpClientResponse(200, [], 'invalid json');
        $this->assertNull($response->json());
    }

    public function testJsonWithKeyReturnsNestedValue()
    {
        $body = json_encode(['data' => ['user' => ['name' => 'John']]]);
        $response = new HttpClientResponse(200, [], $body);

        $this->assertEquals(['user' => ['name' => 'John']], $response->json('data'));
        $this->assertEquals(['name' => 'John'], $response->json('data.user'));
        $this->assertEquals('John', $response->json('data.user.name'));
    }

    public function testJsonWithKeyReturnsDefaultForMissingKey()
    {
        $body = json_encode(['key' => 'value']);
        $response = new HttpClientResponse(200, [], $body);

        $this->assertEquals('default', $response->json('missing', 'default'));
        $this->assertNull($response->json('missing'));
    }

    public function testJsonWithKeyReturnsDefaultForInvalidJson()
    {
        $response = new HttpClientResponse(200, [], 'invalid');

        $this->assertEquals('default', $response->json('key', 'default'));
    }

    public function testObjectReturnsJsonAsObject()
    {
        $body = json_encode(['key' => 'value']);
        $response = new HttpClientResponse(200, [], $body);
        $object = $response->object();

        $this->assertIsObject($object);
        $this->assertEquals('value', $object->key);
    }

    public function testObjectReturnsNullForInvalidJson()
    {
        $response = new HttpClientResponse(200, [], 'invalid json');
        $this->assertNull($response->object());
    }

    public function testCollectReturnsCollection()
    {
        $body = json_encode(['items' => [1, 2, 3]]);
        $response = new HttpClientResponse(200, [], $body);
        $collection = $response->collect('items');

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals([1, 2, 3], $collection->toArray());
    }

    public function testCollectReturnsEmptyCollectionForInvalidJson()
    {
        $response = new HttpClientResponse(200, [], 'invalid');
        $collection = $response->collect();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals([], $collection->toArray());
    }

    public function testCollectReturnsEmptyCollectionForNonArray()
    {
        $body = json_encode('string');
        $response = new HttpClientResponse(200, [], $body);
        $collection = $response->collect();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals([], $collection->toArray());
    }

    public function testResourceReturnsResource()
    {
        $response = new HttpClientResponse(200, [], 'test content');
        $resource = $response->resource();

        $this->assertIsResource($resource);
        $this->assertEquals('test content', stream_get_contents($resource));
        fclose($resource);
    }

    public function testToArrayReturnsArrayFromJson()
    {
        $body = json_encode(['key' => 'value']);
        $response = new HttpClientResponse(200, [], $body);

        $this->assertEquals(['key' => 'value'], $response->toArray());
    }

    public function testToArrayReturnsEmptyArrayForInvalidJson()
    {
        $response = new HttpClientResponse(200, [], 'invalid json');
        $this->assertEquals([], $response->toArray());
    }

    public function testToStringReturnsBody()
    {
        $response = new HttpClientResponse(200, [], 'test body');
        $this->assertEquals('test body', (string)$response);
    }
}