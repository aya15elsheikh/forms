<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class FormFieldController extends Controller
{
    public function index(Form $form)
    {
        return response()->json([
            'success' => true,
            'data' => $form->fields()->orderBy('order')->get()
        ]);
    }

    public function store(Request $request, Form $form)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,email,number,textarea,select,radio,checkbox,file,date',
            'required' => 'boolean',
            'placeholder' => 'nullable|string',
            'help_text' => 'nullable|string',
            'options' => 'nullable|array',
            'options.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['name'] = Str::slug($request->label, '_');
        $data['form_id'] = $form->id;
        $data['order'] = $form->fields()->max('order') + 1;

        $field = FormField::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Field added successfully',
            'data' => $field
        ], 201);
    }

    public function show(FormField $field)
    {
        return response()->json([
            'success' => true,
            'data' => $field
        ]);
    }

    public function update(Request $request, FormField $field)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,email,number,textarea,select,radio,checkbox,file,date',
            'required' => 'boolean',
            'placeholder' => 'nullable|string',
            'help_text' => 'nullable|string',
            'options' => 'nullable|array',
            'options.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $data['name'] = Str::slug($request->label, '_');

        $field->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Field updated successfully',
            'data' => $field->fresh()
        ]);
    }

    public function destroy(FormField $field)
    {
        $field->delete();

        return response()->json([
            'success' => true,
            'message' => 'Field deleted successfully'
        ]);
    }

    public function reorder(Request $request, Form $form)
    {
        $validator = Validator::make($request->all(), [
            'fields' => 'required|array',
            'fields.*' => 'exists:form_fields,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->fields as $index => $fieldId) {
            FormField::where('id', $fieldId)
                ->where('form_id', $form->id)
                ->update(['order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fields reordered successfully'
        ]);
    }

    public function duplicate(FormField $field)
    {
        $newField = $field->replicate();
        $newField->label = $field->label . ' (Copy)';
        $newField->name = Str::slug($newField->label, '_');
        $newField->order = $field->form->fields()->max('order') + 1;
        $newField->save();

        return response()->json([
            'success' => true,
            'message' => 'Field duplicated successfully',
            'data' => $newField
        ], 201);
    }
}
