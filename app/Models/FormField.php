<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    protected $fillable = [
        'form_id',
        'label',
        'name',
        'type',
        'options',
        'required',
        'placeholder',
        'help_text',
        'order'
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean'
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
