# nitron/sdk

کلاینت PHP برای گیت‌وی نیترون، بدون فریم‌ورک. HTTP با curl. نام‌فضا `Nitron\Sdk`.

PHP 8.1 و جدیدتر، روی یک کد. نحو همان ۸.۱ است تا ۸.۲، ۸.۳ و نسخه‌های بعدی همین بسته را اجرا کنند. مبلغ‌ها ریال‌اند و همان عدد پاسخ سرور برمی‌گردند.

آدرس پایه فقط مبدأ است، مثل `https://api.example.ir`. مسیرها زیر `/api/v1/gateway` ساخته می‌شوند. احراز هویت با هدر `X-API-Key`.

```php
<?php

use Nitron\Sdk\ApiException;
use Nitron\Sdk\Client;

$nitron = new Client('https://api.example.ir', 'nit_test_...');

try {
    $balance = $nitron->walletBalance();
    $availableRials = $balance['data']['available_balance'];
} catch (ApiException $e) {
    // $e->status() کد HTTP، $e->code() کد خطا، $e->getMessage() متن سرور
}
```

`code()` اگر `error_code` در JSON باشد همان است (مثل `missing_api_key`). وگرنه مقدار `code` پاسخ است که در خطاهای `httpx` همان کد HTTP است. اگر هیچ‌کدام نباشد، خود status به‌صورت رشته برمی‌گردد. فیلدهای اضافهٔ بدنه، از جمله `reservation_id` در خطای ۵۰۴، با `$e->body()` در دسترس است.

## صورتحساب

`execute` به `POST /api/v1/gateway/hub/execute` می‌رود. `unit_price` اقلام به ریال است. اگر آرگومان دوم یا `idempotency_key` داخل بدنه پر باشد، هدر `Idempotency-Key` هم فرستاده می‌شود. خودِ بدنه بازنویسی نمی‌شود؛ اگر هر دو باشند، سرور اول مقدار داخل بدنه را برمی‌دارد.

```php
$invoice = $nitron->execute([
    'service_code' => 'pdf_generate',
    'variant' => 'invoice',
    'units' => 1,
    'idempotency_key' => 'invoice-1405-101',
    'params' => [
        'template' => 'tpl_REPLACE',
        'data' => [
            'number' => '1405-101',
            'seller' => ['name' => 'شرکت نمونه'],
            'buyer' => ['name' => 'فروشگاه نمونه'],
            'items' => [
                ['title' => 'خدمات', 'qty' => 1, 'unit_price' => 12500000, 'discount' => 0],
            ],
        ],
    ],
]);

$costRials = $invoice['data']['total_cost'];
```

## تلگرام

`sendTelegram` فقط `POST /api/v1/gateway/telegram/send` است. بقیهٔ مسیرهای تلگرام با `request` صدا زده می‌شوند.

```php
$telegram = $nitron->sendTelegram([
    'chat_id' => '123456789',
    'type' => 'text',
    'text' => 'سلام {{name}}',
    'parse_mode' => 'HTML',
    'variables' => ['name' => 'علی'],
    'buttons' => [[['text' => 'سایت', 'url' => 'https://example.ir']]],
    'idempotency_key' => 'tg-hello-ali',
]);
```

پاسخ موفق این مسیر کد ۲۰۲ است. `total_estimated_cost` و `estimated_cost` به ریال‌اند.

## بله

`sendBale` فقط `POST /api/v1/gateway/bale/send` است.

```php
$bale = $nitron->sendBale([
    'chat_id' => '@accounting',
    'type' => 'text',
    'text' => "فاکتور * {{number}} * ثبت شد.\nخریدار: {{buyer.name}}\nمبلغ: {{amount}} ریال\n[مشاهده در پنل](https://example.ir/invoices/{{number}})",
    'variables' => [
        'number' => '1405-101',
        'amount' => '12500000',
        'buyer' => ['name' => 'فروشگاه نمونه'],
    ],
    'idempotency_key' => 'bale-invoice-1405-101',
]);
```

`amount` متن جایگزین‌شونده است و باید ریال باشد. کلاینت آن را به تومان تبدیل نمی‌کند.

## OTP

`sendSms` به `POST /api/v1/gateway/sms/send` می‌رود. بدنه همان فیلدهای هندلر است: `to`، `template_code`، `code`، `service_code`. برای OTP مقدار `service_code` برابر `sms_otp` است. متن آزاد برای این سرویس فرستاده نمی‌شود.

```php
$otp = $nitron->sendSms([
    'to' => '09120000000',
    'template_code' => 'login',
    'code' => '483920',
    'service_code' => 'sms_otp',
    'idempotency_key' => 'otp-09120000000-483920',
]);

$costRials = $otp['data']['total_cost'];
```

## بقیهٔ مسیرها

`request` هر مسیری را که متد اختصاصی ندارد صدا می‌زند. مسیر بدون اسلش اول، به `/api/v1/gateway/` چسبانده می‌شود.

```php
$me = $nitron->me();
$usage = $nitron->request('GET', 'usage', null, ['page' => 1, 'per_page' => 20]);
$otpState = $nitron->request('GET', 'otp/' . $otp['data']['reference_id']);
$messages = $nitron->request('GET', 'telegram/messages');
```

مسیرهای گیت‌وی که متد اختصاصی دارند: `GET /me`، `GET /wallet/balance`، `POST /hub/execute`، `POST /sms/send`، `POST /telegram/send`، `POST /bale/send`. از همین کلاینت این‌ها هم با `request` در دسترس‌اند: `GET /otp/templates`، `GET /otp/{uuid}`، `GET /wallet/transactions`، `GET /usage`، `POST /payment/request`، و مسیرهای زیر `/telegram` و `/bale` به‌جز اینکه ارسال همان دو متد بالاست.

## نصب

از داخل همین پوشه:

```bash
composer install
composer test
```

ریپوی والد برای نصب از گیت یک `composer.json` در ریشه دارد تا پلاگین فقط بستهٔ `nitron/sdk` را بگیرد. توضیح چیدمان زبان‌ها در README ریشه است.
