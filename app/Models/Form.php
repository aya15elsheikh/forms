<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Form extends Model
{

       protected $fillable = [
        'title',
        'description',
        'is_active',
        'opens_at',
        'closes_at'
    ];

    protected $casts = [
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    public function fields()
    {
        return $this->hasMany(FormField::class)->orderBy('order');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function isOpen()
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        
        if ($this->opens_at && $now->lt($this->opens_at)) {
            return false;
        }

        if ($this->closes_at && $now->gt($this->closes_at)) {
            return false;
        }

        return true;
    }
    
}
