<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Family extends Model
{
    protected $fillable = [
        'name',
        'email',
        'whatsapp',
        'country',
        'currency',
        'timezone',
        'notes',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the students for the family.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
