# Nitron SDK

هر زبان یک پوشه است. برنامه‌ای که به یک زبان وصل می‌شود فقط همان پوشه را لازم دارد.

| پوشه | وضعیت |
| --- | --- |
| `nitron-sdk-php` | کلاینت PHP، بستهٔ Composer با نام `nitron/sdk` |
| `nitron-sdk-python` | جا برای کلاینت پایتون |
| `nitron-sdk-go` | جا برای کلاینت Go |
| `nitron-sdk-node` | جا برای جاوااسکریپت، تایپ‌اسکریپت و React Native |
| `nitron-sdk-java` | جا برای جاوا و اندروید |
| `nitron-sdk-kotlin` | جا برای کاتلین و اندروید |
| `nitron-sdk-swift` | جا برای سوئیفت و iOS |
| `nitron-sdk-dart` | جا برای دارت و Flutter |
| `nitron-sdk-csharp` | جا برای .NET و .NET MAUI |

نسخه با تگ گیت منتشر می‌شود (`v1.2.3`). خط ۱ این SDK با `/api/v1/gateway` یکی است. سازگاری PHP 8.1 به بالا داخل همان بستهٔ PHP است، نه با یک پوشه برای هر نسخه از PHP.

پلاگین PHP این ریپو را با یک قید نسخه می‌خواند:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://example.com/nitron-sdk.git"}
    ],
    "require": {
        "nitron/sdk": "^1.0"
    }
}
```

`composer.json` ریشه همان بسته است و کلاس‌ها را از `nitron-sdk-php/src` بار می‌کند. پوشه‌های زبان‌های دیگر در `.gitattributes` با `export-ignore` علامت خورده‌اند تا آرشیو تگ پایدار آن‌ها را همراه PHP نفرستد. `nitron-sdk-php/composer.json` همان بسته را برای توسعه و برای وقتی که این پوشه جدا منتشر شود نگه می‌دارد.

نمونهٔ صورتحساب، تلگرام، بله و OTP در [nitron-sdk-php/README.md](nitron-sdk-php/README.md) است.
