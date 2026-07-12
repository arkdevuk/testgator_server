<?php

declare(strict_types=1);

namespace App\Serializer;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_INVALID_UTF8_IGNORE;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_UNESCAPED_UNICODE;

use Symfony\Component\Serializer\Encoder\JsonEncode;

/**
 * Same encoding options as API Platform's default JsonEncoder
 * (HTML-safe escaping) plus JSON_PRESERVE_ZERO_FRACTION so float
 * values keep their decimal point (e.g. "percentage": 200.0, not 200).
 */
final class ApiJsonEncode extends JsonEncode
{
    public function __construct()
    {
        parent::__construct([
            self::OPTIONS => JSON_HEX_TAG
                | JSON_HEX_APOS
                | JSON_HEX_AMP
                | JSON_HEX_QUOT
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_IGNORE
                | JSON_PRESERVE_ZERO_FRACTION,
        ]);
    }
}
