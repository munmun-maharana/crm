<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationSetting extends Model
{
    protected $fillable = [
        'status',
        'title',
        'body',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'status' => 'converted',
            'title' => 'Assigned to Operation Team',
            'body' => 'Lead handed over to Operation Team.',
        ]);
    }
}
