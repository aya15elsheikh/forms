<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Validator;


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;


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


    public function exportSubmissionsExcel(Form $form)
    {
        $form->load('fields');
        $submissions = $form->submissions()->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Submission ID');
        $sheet->setCellValue('B1', 'Student Name');
        $sheet->setCellValue('C1', 'Student Email');

        $colIndex = 4;
        foreach ($form->fields as $field) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($columnLetter . '1', $field->label);
            $colIndex++;
        }


        $rowIndex = 2;
        foreach ($submissions as $submission) {
            $sheet->setCellValue("A{$rowIndex}", $submission->id);
            $sheet->setCellValue("B{$rowIndex}", $submission->student_name);
            $sheet->setCellValue("C{$rowIndex}", $submission->student_email);
            $colIndex = 4;
            foreach ($form->fields as $field) {
                $value = $submission->data[$field->name] ?? '';

                if (is_array($value)) {
                    $value = implode(', ', $value);
                } elseif (is_array(json_decode($value, true))) {
                    $value = implode(', ', json_decode($value, true));
                }
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($columnLetter . $rowIndex, $value);
                $colIndex++;
            }

            $rowIndex++;
        }

        if (ob_get_length()) ob_end_clean();

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $form->title . '_submissions.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }



    public function importSubmissionsExcel(Request $request, Form $form)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            $form->load('fields');
            $fieldMap = $form->fields->pluck('name', 'label'); // [label => name]

            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $header = $rows[0];
            $dataRows = array_slice($rows, 1);

            foreach ($dataRows as $row) {
                $rowData = array_combine($header, $row);

                $submissionData = [];
                foreach ($fieldMap as $label => $name) {
                    $submissionData[$name] = $rowData[$label] ?? null;
                }

                FormSubmission::create([
                    'form_id' => $form->id,
                    'student_name' => $rowData['Student Name'] ?? 'Imported',
                    'student_email' => $rowData['Student Email'] ?? 'noemail@example.com',
                    'data' => $submissionData,
                    'submitted_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Submissions imported successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
