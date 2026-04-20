<?php

use PHPUnit\Framework\TestCase;

class MiraklClientTest extends TestCase
{
    public function test_authorization_header_is_raw_key_without_bearer_prefix()
    {
        $captured = null;
        $client = new MiraklClient('https://x.mirakl.net/api', 'my-key-123');
        $client->setTransport(function ($request) use (&$captured) {
            $captured = $request;

            return ['status_code' => 200, 'headers' => [], 'body' => '{"shop_id":42}'];
        });

        $client->get('/account');

        $this->assertContains('Authorization: my-key-123', $captured['headers']);
        $this->assertContains('Accept: application/json', $captured['headers']);
    }

    public function test_200_response_decodes_json_body()
    {
        $client = new MiraklClient('https://x.mirakl.net/api', 'k');
        $client->setTransport(function ($r) {
            return ['status_code' => 200, 'headers' => [], 'body' => '{"shop_id":42,"shop_name":"Test"}'];
        });

        $resp = $client->get('/account');

        $this->assertEquals(200, $resp['status_code']);
        $this->assertEquals(42, $resp['decoded']['shop_id']);
        $this->assertEquals('Test', $resp['decoded']['shop_name']);
    }

    public function test_url_is_built_from_endpoint_path_and_query()
    {
        $captured = null;
        $client = new MiraklClient('https://x.mirakl.net/api', 'k');
        $client->setTransport(function ($r) use (&$captured) {
            $captured = $r;

            return ['status_code' => 200, 'headers' => [], 'body' => '{}'];
        });

        $client->get('/orders', ['order_state_codes' => 'WAITING_ACCEPTANCE', 'limit' => 10]);

        $this->assertStringContainsString('https://x.mirakl.net/api/orders', $captured['url']);
        $this->assertStringContainsString('order_state_codes=WAITING_ACCEPTANCE', $captured['url']);
        $this->assertStringContainsString('limit=10', $captured['url']);
    }

    public function test_post_body_is_json_encoded_with_content_type_header()
    {
        $captured = null;
        $client = new MiraklClient('https://x.mirakl.net/api', 'k');
        $client->setTransport(function ($r) use (&$captured) {
            $captured = $r;

            return ['status_code' => 200, 'headers' => [], 'body' => '{}'];
        });

        $client->post('/offers', ['offers' => [['sku' => 'X1', 'price' => 9.99]]]);

        $this->assertEquals('POST', $captured['method']);
        $this->assertContains('Content-Type: application/json', $captured['headers']);
        $this->assertStringContainsString('"sku":"X1"', $captured['body']);
    }

    public function test_401_throws_auth_exception_without_retry()
    {
        $calls = 0;
        $client = new MiraklClient('https://x.mirakl.net/api', 'bad');
        $client->setTransport(function ($r) use (&$calls) {
            $calls++;

            return ['status_code' => 401, 'headers' => [], 'body' => '{"message":"Unauthorized"}'];
        });

        try {
            $client->get('/account');
            $this->fail('Expected MiraklAuthException');
        } catch (MiraklAuthException $e) {
            $this->assertEquals(401, $e->getStatusCode());
            $this->assertEquals(1, $calls, '401 must not retry');
        }
    }

    public function test_404_throws_not_found_exception()
    {
        $client = new MiraklClient('https://x.mirakl.net/api', 'k');
        $client->setTransport(function ($r) {
            return ['status_code' => 404, 'headers' => [], 'body' => ''];
        });

        $this->expectException(MiraklNotFoundException::class);
        $client->get('/missing');
    }

    public function test_429_retries_and_eventually_succeeds()
    {
        $client = new MiraklClient('https://x.mirakl.net/api', 'k');
        $calls = 0;
        $client->setTransport(function ($r) use (&$calls) {
            $calls++;
            if ($calls < 3) {
                return ['status_code' => 429, 'headers' => [], 'body' => '{"message":"Too many"}'];
            }

            return ['status_code' => 200, 'headers' => [], 'body' => '{"ok":true}'];
        });

        $resp = $client->get('/x');
        $this->assertEquals(200, $resp['status_code']);
        $this->assertEquals(3, $calls);
    }

    public function test_500_retries_up_to_max_then_throws_server_exception()
    {
        $client = new MiraklClient('https://x.mirakl.net/api', 'k');
        $calls = 0;
        $client->setTransport(function ($r) use (&$calls) {
            $calls++;

            return ['status_code' => 500, 'headers' => [], 'body' => 'oops'];
        });

        try {
            $client->get('/x', [], ['max_attempts' => 3]);
            $this->fail('Expected MiraklServerException');
        } catch (MiraklServerException $e) {
            $this->assertEquals(500, $e->getStatusCode());
            $this->assertEquals(3, $calls);
        }
    }

    public function test_400_throws_validation_exception_and_does_not_retry()
    {
        $client = new MiraklClient('https://x.mirakl.net/api', 'k');
        $calls = 0;
        $client->setTransport(function ($r) use (&$calls) {
            $calls++;

            return [
                'status_code' => 400,
                'headers' => [],
                'body' => '{"errors":[{"error_code":"OF-23","message":"Bad SKU"}]}',
            ];
        });

        try {
            $client->post('/offers', ['x' => 1]);
            $this->fail('Expected MiraklValidationException');
        } catch (MiraklValidationException $e) {
            $this->assertEquals(400, $e->getStatusCode());
            $this->assertEquals('OF-23', $e->getErrorCode());
            $this->assertEquals('Bad SKU', $e->getMessage());
            $this->assertEquals(1, $calls);
        }
    }

    public function test_network_exception_retries_and_then_bubbles_up()
    {
        $client = new MiraklClient('https://x.mirakl.net/api', 'k');
        $calls = 0;
        $client->setTransport(function ($r) use (&$calls) {
            $calls++;

            throw new MiraklNetworkException('Connection refused');
        });

        try {
            $client->get('/x', [], ['max_attempts' => 3]);
            $this->fail('Expected MiraklNetworkException');
        } catch (MiraklNetworkException $e) {
            $this->assertEquals(3, $calls);
        }
    }

    public function test_test_connection_returns_decoded_account_payload()
    {
        $client = new MiraklClient('https://x.mirakl.net/api', 'k');
        $client->setTransport(function ($r) {
            return [
                'status_code' => 200,
                'headers' => [],
                'body' => '{"shop_id":777,"shop_name":"Acme","currency_iso_code":"EUR"}',
            ];
        });

        $result = $client->testConnection();

        $this->assertIsArray($result);
        $this->assertEquals(777, $result['shop_id']);
        $this->assertEquals('EUR', $result['currency_iso_code']);
    }

    public function test_non_json_response_body_is_still_returned_raw()
    {
        $client = new MiraklClient('https://x.mirakl.net/api', 'k');
        $client->setTransport(function ($r) {
            return ['status_code' => 200, 'headers' => [], 'body' => 'plain text'];
        });

        $resp = $client->get('/x');
        $this->assertEquals('plain text', $resp['body']);
        $this->assertNull($resp['decoded']);
    }
}
