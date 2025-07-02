<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Form;    
use Illuminate\Support\Facades\Validator;
class AdminFormController extends Controller
{
    public function index()
    {
        $forms = Form::with(['fields', 'submissions'])->get();
        
        return response()->json([
            'success' => true,
            'data' => $forms->map(function ($form) {
                return [
                    'id' => $form->id,
                    'title' => $form->title,
                    'description' => $form->description,
                    'is_active' => $form->is_active,
                    'opens_at' => $form->opens_at,
                    'closes_at' => $form->closes_at,
                    'is_open' => $form->isOpen(),
                    'fields_count' => $form->fields->count(),
                    'submissions_count' => $form->submissions->count(),
                    'created_at' => $form->created_at,
                    'updated_at' => $form->updated_at,
                ];
            })
        ]);
    }

    public function show(Form $form)
    {
        $form->load('fields');
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $form->id,
                'title' => $form->title,
                'description' => $form->description,
                'is_active' => $form->is_active,
                'opens_at' => $form->opens_at,
                'closes_at' => $form->closes_at,
                'is_open' => $form->isOpen(),
                'fields' => $form->fields,
                'created_at' => $form->created_at,
                'updated_at' => $form->updated_at,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after:opens_at',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $form = Form::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Form created successfully',
            'data' => $form
        ], 201);
    }

    public function update(Request $request, Form $form)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after:opens_at',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $form->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Form updated successfully',
            'data' => $form
        ]);
    }

    public function destroy(Form $form)
    {
        $form->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Form deleted successfully'
        ]);
    }

    public function submissions(Form $form)
    {
        $submissions = $form->submissions()
            ->latest()
            ->paginate(request('per_page', 20));

            // if submsisson field is file return full url
        $submissions->each(function ($submission) use ($form) {
            $formData = $submission->data;          
            foreach ($form->fields as $field) {
                if (isset($formData[$field->name]) && is_array($formData[$field->name]) && isset($formData[$field->name]['path'])) {
                    $formData[$field->name]['url'] = asset('storage/' . $formData[$field->name]['path']);
                    // drop path
                    unset($formData[$field->name]['path']);
                    unset($formData[$field->name]['original_name']);
                    unset($formData[$field->name]['size']);
                    unset($formData[$field->name]['mime_type']);
                }
            }
            $submission->data = $formData;
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'form' => [
                    'id' => $form->id,
                    'title' => $form->title,
                    'fields' => $form->fields
                ],
                'submissions' => $submissions
            ]
        ]);
    }

    public function exportSubmissions(Form $form)
    {
        $submissions = $form->submissions()->with('form.fields')->get();

        // if submission field is file return full url
        $submissions->each(function ($submission) use ($form) {
            $formData = $submission->data;
            foreach ($form->fields as $field) {
                if (isset($formData[$field->name]) && is_array($formData[$field->name]) && isset($formData[$field->name]['path'])) {
                    $formData[$field->name]['url'] = asset('storage/' . $formData[$field->name]['path']);
                    // drop path
                    unset($formData[$field->name]['path']);
                    unset($formData[$field->name]['original_name']);
                    unset($formData[$field->name]['size']);
                    unset($formData[$field->name]['mime_type']);
                }
            }
            $submission->data = $formData;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'form_title' => $form->title,
                'submissions' => $submissions->map(function ($submission) {
                    return [
                        'id' => $submission->id,
                        'student_email' => $submission->student_email,
                        'data' => $submission->data,
                        'submitted_at' => $submission->submitted_at,
                    ];
                })
            ]
        ]);
    }
}
