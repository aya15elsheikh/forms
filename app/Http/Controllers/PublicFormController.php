<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Validator;

class PublicFormController extends Controller
{

    public function index()
    {
        $forms = Form::where('is_active', true)
            ->whereRaw('(opens_at IS NULL OR opens_at <= NOW())')
            ->whereRaw('(closes_at IS NULL OR closes_at >= NOW())')
            ->select('id', 'title', 'description', 'opens_at', 'closes_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $forms->map(function ($form) {
                return [
                    'id' => $form->id,
                    'title' => $form->title,
                    'description' => $form->description,
                    'opens_at' => $form->opens_at,
                    'closes_at' => $form->closes_at,
                    'is_open' => $form->isOpen(),
                ];
            })
        ]);
    }

    public function show(Form $form)
    {
        if (!$form->isOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Form is not available at this time'
            ], 404);
        }

        $form->load('fields');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $form->id,
                'title' => $form->title,
                'description' => $form->description,
                'fields' => $form->fields->map(function ($field) {
                    return [
                        'id' => $field->id,
                        'name' => $field->name,
                        'label' => $field->label,
                        'type' => $field->type,
                        'required' => $field->required,
                        'placeholder' => $field->placeholder,
                        'help_text' => $field->help_text,
                        'options' => $field->options,
                        'order' => $field->order,
                    ];
                }),
                'opens_at' => $form->opens_at,
                'closes_at' => $form->closes_at,
            ]
        ]);
    }

    public function submit(Request $request, Form $form)
    {
        if (!$form->isOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Form is not available for submission'
            ], 400);
        }

        // Build validation rules dynamically
        $rules = [
            'student_email' => 'required|email|max:255'
        ];

        foreach ($form->fields as $field) {
            $fieldRules = [];

            if ($field->required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field->type) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'file':
                    $fieldRules[] = 'file|max:2048';
                    break;
                case 'text':
                    $fieldRules[] = 'string|max:1000';
                    break;
                case 'textarea':
                    $fieldRules[] = 'string|max:5000';
                    break;
                case 'select':
                    if ($field->options) {
                        $options = array_map('trim', $field->options);
                        $fieldRules[] = 'in:' . implode(',', $options);
                    }
                    break;
                case 'checkbox':
                    if ($field->options) {
                        $options = array_map('trim', $field->options);
                        $fieldRules[] = 'array';
                        $rules[$field->name . '.*'] = 'in:' . implode(',', $options);
                    }
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
            }

            $rules[$field->name] = implode('|', $fieldRules);
        }

        // if data field is file or  img save to storage

        $formData = [];
        foreach ($form->fields as $field) {
            if (
                ($field->type === 'file' || $field->type === 'image')
                && $request->hasFile($field->name)
            ) {
                $file = $request->file($field->name);
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('form_uploads', $filename, 'public');
                $formData[$field->name] = [
                    'path' => $path,
                ];
            } else {
                $formData[$field->name] = $request->input($field->name);
            }
        }


        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        // Handle file uploads
        $formData = [];
        foreach ($form->fields as $field) {
            if ($field->type === 'file' && $request->hasFile($field->name)) {
                $file = $request->file($field->name);
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('form_uploads', $filename, 'public');
                $formData[$field->name] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType()
                ];
            } else {
                $formData[$field->name] = $request->input($field->name);
            }
        }

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'student_email' => $validatedData['student_email'],
            'data' => $formData,
            'submitted_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully',
            'data' => [
                'submission_id' => $submission->id,
                'submitted_at' => $submission->submitted_at
            ]
        ], 201);
    }

    public function getSubmission(Form $form, $submissionId)
    {
        $submission = $form->submissions()
            ->where('id', $submissionId)
            ->first();

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }

        //if a field is file return the right path
        $formData = $submission->data;
        foreach ($form->fields as $field) {
            if (isset($formData[$field->name]) && is_array($formData[$field->name]) && isset($formData[$field->name]['path'])) {
                $formData[$field->name]['url'] = asset('storage/' . $formData[$field->name]['path']);
                //drop path
                unset($formData[$field->name]['path']);
                unset($formData[$field->name]['original_name']);
                unset($formData[$field->name]['size']);
                unset($formData[$field->name]['mime_type']);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $submission->id,
                'student_email' => $submission->student_email,
                'form_data' => $formData,
                'submitted_at' => $submission->submitted_at,
                'form' => [
                    'id' => $form->id,
                    'title' => $form->title
                ]
            ]
        ]);
    }
}
