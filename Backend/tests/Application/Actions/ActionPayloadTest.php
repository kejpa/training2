<?php

declare(strict_types=1);

namespace Tests\Application\Actions;

use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use PHPUnit\Framework\TestCase;

class ActionPayloadTest extends TestCase {
    public function testDefaultStatusCodeIs200(): void {
        $payload = new ActionPayload();

        $this->assertEquals(200, $payload->getStatusCode());
    }

    public function testCanSetStatusCode(): void {
        $payload = new ActionPayload(404);

        $this->assertEquals(404, $payload->getStatusCode());
    }

    public function testDefaultDataIsNull(): void {
        $payload = new ActionPayload();

        $this->assertNull($payload->getData());
    }

    public function testCanSetArrayData(): void {
        $data = ['key' => 'value'];
        $payload = new ActionPayload(200, $data);

        $this->assertEquals($data, $payload->getData());
    }

    public function testCanSetObjectData(): void {
        $data = new \stdClass();
        $data->key = 'value';
        $payload = new ActionPayload(200, $data);

        $this->assertEquals($data, $payload->getData());
    }

    public function testDefaultErrorIsNull(): void {
        $payload = new ActionPayload();

        $this->assertNull($payload->getError());
    }

    public function testCanSetError(): void {
        $error = new ActionError(ActionError::NOT_FOUND, 'Resource not found');
        $payload = new ActionPayload(404, null, $error);

        $this->assertEquals($error, $payload->getError());
    }

    public function testJsonSerializeWithOnlyStatusCode(): void {
        $payload = new ActionPayload(200);

        $json = $payload->jsonSerialize();

        $this->assertArrayHasKey('statusCode', $json);
        $this->assertEquals(200, $json['statusCode']);
        $this->assertArrayNotHasKey('data', $json);
        $this->assertArrayNotHasKey('error', $json);
    }

    public function testJsonSerializeWithData(): void {
        $data = ['user' => 'Anna'];
        $payload = new ActionPayload(200, $data);

        $json = $payload->jsonSerialize();

        $this->assertArrayHasKey('statusCode', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayNotHasKey('error', $json);
        $this->assertEquals($data, $json['data']);
    }

    public function testJsonSerializeWithError(): void {
        $error = new ActionError(ActionError::NOT_FOUND, 'Resource not found');
        $payload = new ActionPayload(404, null, $error);

        $json = $payload->jsonSerialize();

        $this->assertArrayHasKey('statusCode', $json);
        $this->assertArrayHasKey('error', $json);
        $this->assertArrayNotHasKey('data', $json);
        $this->assertEquals($error, $json['error']);
    }

    public function testJsonSerializeDataTakesPrecedenceOverError(): void {
        $data = ['key' => 'value'];
        $error = new ActionError(ActionError::NOT_FOUND, 'Resource not found');
        $payload = new ActionPayload(200, $data, $error);

        $json = $payload->jsonSerialize();

        // Data ska visas, inte error, när båda finns
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayNotHasKey('error', $json);
    }

    public function testCanSerializeToJsonString(): void {
        $data = ['user' => 'Anna'];
        $payload = new ActionPayload(200, $data);

        $jsonString = json_encode($payload);
        $decoded = json_decode($jsonString, true);

        $this->assertIsString($jsonString);
        $this->assertEquals(200, $decoded['statusCode']);
        $this->assertEquals($data, $decoded['data']);
    }
}