<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ExternalServiceSetting extends Model
{
    protected $fillable = [
        'service',
        'key',
        'value',
        'encrypted_value',
        'is_secret',
    ];

    protected $casts = [
        'is_secret' => 'boolean',
    ];

    public function resolvedValue(): ?string
    {
        if (! $this->is_secret) {
            return $this->value;
        }

        if ($this->encrypted_value === null || trim($this->encrypted_value) === '') {
            return null;
        }

        return Crypt::decryptString($this->encrypted_value);
    }
}
