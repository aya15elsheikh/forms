<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
       protected $fillable = [
        'form_id',
        'student_name',
        'student_email',
        'data',
        'submitted_at'
    ];

    protected $casts = [
        'data' => 'array',
        'submitted_at' => 'datetime'
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
