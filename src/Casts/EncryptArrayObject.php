<?php

namespace Inovector\Mixpost\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Support\Facades\Crypt;

class EncryptArrayObject implements CastsAttributes
{
    public function get($model, $key, $value, $attributes): ?ArrayObject
    {
        if (isset($attributes[$key])) {
            try {
                return new ArrayObject(json_decode(Crypt::decryptString($attributes[$key]), true));
            } catch (DecryptException $exception) {
                // The value cannot be decrypted (e.g. APP_KEY was rotated). Fail soft so
                // the record stays readable instead of throwing on every access.
                return null;
            }
        }

        return null;
    }

    public function set($model, string $key, $value, array $attributes): ?array
    {
        if (! is_null($value)) {
            return [$key => Crypt::encryptString(json_encode($value))];
        }

        return null;
    }
}
