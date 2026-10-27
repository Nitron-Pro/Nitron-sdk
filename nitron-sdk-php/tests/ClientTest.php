<?php

declare(strict_types=1);

namespace Nitron\Sdk\Tests;

use InvalidArgumentException;
use Nitron\Sdk\ApiException;
use Nitron\Sdk\Client;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testExecuteSendsInvoiceInRials(): void
    {
        [$client, $transport] = $this->client(200, $this->fixture('invoice.response.json'));
        $body = $this->json('invoice.request.json');

        $result = $client->execute($body);

        $call = $transport->calls[0];
        self::assertSame('POST', $call['method']);
        self::assertSame('https://gateway.test/api/v1/gateway/hub/execute', $call['url']);
        self::assertSame('nit_test_example', $call['headers']['X-API-Key']);
        self::assertSame('invoice-1405-101', $call['headers']['Idempotency-Key']);
        self::assertSame($body, $this->decode($call['body']));
        self::assertSame(12500000, $result['data']['total_cost']);
        self::assertSame(12500000, $result['data']['unit_price']);
        self::assertSame(0, $result['data']['tax_amount']);
    }

    public function testSendTelegramPostsSendAndIdempotencyHeader(): void
    {
        [$client, $transport] = $this->client(202, $this->fixture('telegram.response.json'));
        $body = $this->json('telegram.request.json');

        $result = $client->sendTelegram($body);

        $call = $transport->calls[0];
        self::assertSame('POST', $call['method']);
        self::assertSame('https://gateway.test/api/v1/gateway/telegram/send', $call['url']);
        self::assertSame('tg-hello-ali', $call['headers']['Idempotency-Key']);
        self::assertSame($body, $this->decode($call['body']));
        self::assertSame(8000, $result['data']['total_estimated_cost']);
        self::assertSame(8000, $result['data']['messages'][0]['estimated_cost']);
    }

    public function testSendBalePostsSendAndIdempotencyHeader(): void
    {
        [$client, $transport] = $this->client(202, $this->fixture('bale.response.json'));
        $body = $this->json('bale.request.json');

        $result = $client->sendBale($body);

        $call = $transport->calls[0];
        self::assertSame('POST', $call['method']);
        self::assertSame('https://gateway.test/api/v1/gateway/bale/send', $call['url']);
        self::assertSame('bale-invoice-1405-101', $call['headers']['Idempotency-Key']);
        self::assertSame($body, $this->decode($call['body']));
        self::assertSame(6000, $result['data']['total_estimated_cost']);
    }

    public function testHeaderArgumentDoesNotRewriteTheBody(): void
    {
        [$client, $transport] = $this->client(202, $this->fixture('telegram.response.json'));

        $client->sendTelegram(['chat_id' => '1', 'type' => 'text', 'text' => 'سلام'], 'explicit-key');

        $call = $transport->calls[0];
        self::assertSame('explicit-key', $call['headers']['Idempotency-Key']);
        self::assertSame(
            ['chat_id' => '1', 'type' => 'text', 'text' => 'سلام'],
            $this->decode($call['body'])
        );
    }

    public function testSendSmsUsesOtpFieldsFromTheHandler(): void
    {
        [$client, $transport] = $this->client(200, $this->fixture('otp.response.json'));
        $body = $this->json('otp.request.json');

        $result = $client->sendSms($body);

        $call = $transport->calls[0];
        self::assertSame('POST', $call['method']);
        self::assertSame('https://gateway.test/api/v1/gateway/sms/send', $call['url']);
        self::assertSame('otp-09120000000-483920', $call['headers']['Idempotency-Key']);
        self::assertSame($body, $this->decode($call['body']));
        self::assertArrayNotHasKey('recipients', $this->decode($call['body']));
        self::assertSame(3500, $result['data']['total_cost']);
    }

    public function testMeAndWalletBalance(): void
    {
        [$client, $transport] = $this->client(200, $this->fixture('me.response.json'));
        $me = $client->me();
        self::assertSame('GET', $transport->calls[0]['method']);
        self::assertSame('https://gateway.test/api/v1/gateway/me', $transport->calls[0]['url']);
        self::assertNull($transport->calls[0]['body']);
        self::assertSame('شرکت نمونه', $me['data']['organization']['name']);
        self::assertSame(12500000, $me['data']['project']['spent_amount']);

        [$client, $transport] = $this->client(200, $this->fixture('balance.response.json'));
        $balance = $client->walletBalance();
        self::assertSame('https://gateway.test/api/v1/gateway/wallet/balance', $transport->calls[0]['url']);
        self::assertSame(37500000, $balance['data']['available_balance']);
        self::assertSame('IRR', $balance['data']['currency']);
    }

    public function testModuleContractRoutes(): void
    {
        [$client, $transport] = $this->client(200, '{"status":"success","data":{"paid":false,"unit_price":3500,"html":"<p></p>","idempotency_key":"invoice-1405-101"}}');

        $client->services();
        $client->usage(['from' => '2026-01-01', 'to' => '2026-01-31', 'service_code' => 'sms_otp', 'idempotency_key' => 'otp-1', 'page' => 1]);
        $client->walletTransactions(['type' => 'debit', 'idempotency_key' => 'invoice-1405-101']);
        $client->requestPayment(['amount' => 1000000, 'callback_url' => 'https://shop.example/return']);
        $client->paymentStatus('A0001');
        $client->document('doc-1', ['format' => 'html']);

        self::assertSame('https://gateway.test/api/v1/gateway/services', $transport->calls[0]['url']);
        self::assertSame(
            'https://gateway.test/api/v1/gateway/usage?from=2026-01-01&to=2026-01-31&service_code=sms_otp&idempotency_key=otp-1&page=1',
            $transport->calls[1]['url']
        );
        self::assertStringContainsString('type=debit', $transport->calls[2]['url']);
        self::assertSame('POST', $transport->calls[3]['method']);
        self::assertSame('https://gateway.test/api/v1/gateway/payment/request', $transport->calls[3]['url']);
        self::assertSame(1000000, $this->decode($transport->calls[3]['body'])['amount']);
        self::assertSame('https://gateway.test/api/v1/gateway/payment/A0001', $transport->calls[4]['url']);
        self::assertSame('https://gateway.test/api/v1/gateway/documents/doc-1?format=html', $transport->calls[5]['url']);
    }

    public function testRequestReachesRoutesWithoutADedicatedMethod(): void
    {
        [$client, $transport] = $this->client(200, '{"status":"success","data":[]}');

        $client->request('GET', 'usage', null, ['page' => 2, 'per_page' => 20]);

        self::assertSame(
            'https://gateway.test/api/v1/gateway/usage?page=2&per_page=20',
            $transport->calls[0]['url']
        );
        self::assertSame('nit_test_example', $transport->calls[0]['headers']['X-API-Key']);
    }

    public function testApiKeyCannotBeReplacedByCallerHeaders(): void
    {
        [$client, $transport] = $this->client(200, '{"status":"success","data":{}}');

        $client->request('GET', '/api/v1/gateway/me', null, [], ['X-API-Key' => 'other']);

        self::assertSame('nit_test_example', $transport->calls[0]['headers']['X-API-Key']);
    }

    public function testAuthErrorPrefersErrorCode(): void
    {
        [$client] = $this->client(401, $this->fixture('error-auth.json'));

        try {
            $client->me();
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(401, $e->status());
            self::assertSame('missing_api_key', $e->code());
            self::assertStringContainsString('کلید API', $e->getMessage());
        }
    }

    public function testPaymentErrorUsesNumericJsonCode(): void
    {
        [$client] = $this->client(402, $this->fixture('error-balance.json'));

        try {
            $client->walletBalance();
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(402, $e->status());
            self::assertSame('402', $e->code());
            self::assertSame('موجودی کیف پول کافی نیست', $e->getMessage());
        }
    }

    public function testHubErrorWithoutCodeFallsBackToHttpStatus(): void
    {
        [$client] = $this->client(400, $this->fixture('error-message-only.json'));

        try {
            $client->execute(['service_code' => '']);
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(400, $e->status());
            self::assertSame('400', $e->code());
            self::assertSame('بدنه درخواست نامعتبر است', $e->getMessage());
        }
    }

    public function testTimeoutKeepsReservationOnTheExceptionBody(): void
    {
        [$client] = $this->client(504, $this->fixture('error-timeout.json'));

        try {
            $client->sendSms($this->json('otp.request.json'));
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(504, $e->status());
            self::assertSame('504', $e->code());
            self::assertSame('55555555-5555-5555-5555-555555555555', $e->body()['reservation_id']);
        }
    }

    public function testNonJsonErrorUsesRawBody(): void
    {
        [$client] = $this->client(502, '<html>bad gateway</html>');

        try {
            $client->me();
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(502, $e->status());
            self::assertSame('502', $e->code());
            self::assertSame('<html>bad gateway</html>', $e->getMessage());
            self::assertNull($e->body());
        }
    }

    public function testOversizedIntegerStaysExact(): void
    {
        [$client] = $this->client(200, '{"status":"success","data":{"total_cost":9223372036854775808}}');

        $result = $client->execute(['service_code' => 'pdf_generate']);

        self::assertSame('9223372036854775808', $result['data']['total_cost']);
    }

    public function testEmptyBaseUrlIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Client('///', 'nit_test_example', new FakeTransport(200, '{}'));
    }

    public function testReadmeIncludesInvoiceTelegramBaleAndOtpSamples(): void
    {
        $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');
        foreach ([
            'pdf_generate',
            'variant',
            '12500000',
            'sendTelegram',
            'tg-hello-ali',
            'sendBale',
            '@accounting',
            'sendSms',
            'template_code',
            'sms_otp',
            'ریال',
            'Idempotency-Key',
        ] as $needle) {
            self::assertStringContainsString($needle, $readme);
        }
    }

    /** @return array{0: Client, 1: FakeTransport} */
    private function client(int $status, string $body): array
    {
        $transport = new FakeTransport($status, $body);

        return [new Client('https://gateway.test/', 'nit_test_example', $transport), $transport];
    }

    private function fixture(string $name): string
    {
        $raw = file_get_contents(__DIR__ . '/fixtures/' . $name);
        self::assertIsString($raw);

        return $raw;
    }

    /** @return array<mixed> */
    private function json(string $name): array
    {
        $data = json_decode($this->fixture($name), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /** @return array<mixed> */
    private function decode(?string $raw): array
    {
        self::assertIsString($raw);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
