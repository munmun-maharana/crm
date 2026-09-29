<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSetting extends Model
{
    protected $fillable = [
        'phone',
        'message_template',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'phone' => '',
            'message_template' => null,
        ]);
    }

    public function normalizedPhone(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);
        if ($digits === '') {
            return null;
        }

        // If 10 digits (common for India without country code), default to country code 91
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return $digits;
    }
}
