<?php

namespace App\Support\Dictionary;

use InvalidArgumentException;
use Normalizer;
use stdClass;

class DictionaryText
{
    public static function spelling(string $value): string
    {
        if (! class_exists(Normalizer::class)) {
            throw new InvalidArgumentException('Dictionary normalization requires the PHP intl extension.');
        }
        $normalized = Normalizer::normalize($value, Normalizer::FORM_C);
        if ($normalized === false) {
            throw new InvalidArgumentException('Spelling must be valid UTF-8.');
        }
        $normalized = preg_replace('/^\s+|\s+$/u', '', $normalized) ?? '';
        if ($normalized === '' || mb_strlen($normalized) > 255 || preg_match('/\p{Cc}/u', $normalized)) {
            throw new InvalidArgumentException('Spelling must contain 1–255 characters without control characters.');
        }

        return $normalized;
    }

    public static function canonical(mixed $value): string
    {
        return json_encode(self::sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function sort(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);

            return (object) array_map(self::sort(...), $properties);
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }

            return array_map(self::sort(...), $value);
        }

        return $value;
    }
}
