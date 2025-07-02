<?php
use App\Http\Controllers\AdminFormController;
use Illuminate\Support\Facades\Route;
// use Illuminate\Routing\Route;

// List all forms
Route::get('forms', [AdminFormController::class, 'index']);

// Show a single form
Route::get('forms/{form}', [AdminFormController::class, 'show']);

// Create a new form
Route::post('forms', [AdminFormController::class, 'store']);

// Update a form
Route::put('forms/{form}', [AdminFormController::class, 'update']);
Route::patch('forms/{form}', [AdminFormController::class, 'update']);

// Delete a form
Route::delete('forms/{form}', [AdminFormController::class, 'destroy']);

// List submissions for a form
Route::get('forms/{form}/submissions', [AdminFormController::class, 'submissions']);

// Export submissions for a form
Route::get('forms/{form}/export-submissions', [AdminFormController::class, 'exportSubmissions']);

use App\Http\Controllers\FormFieldController;

// List all fields for a form
Route::get('forms/{form}/fields', [FormFieldController::class, 'index']);

// Add a new field to a form
Route::post('forms/{form}/fields', [FormFieldController::class, 'store']);

// Show a single field
Route::get('fields/{field}', [FormFieldController::class, 'show']);

// Update a field
Route::put('fields/{field}', [FormFieldController::class, 'update']);

Route::patch('fields/{field}', [FormFieldController::class, 'update']);

// Delete a field
Route::delete('fields/{field}', [FormFieldController::class, 'destroy']);

// Reorder fields for a form
Route::post('forms/{form}/fields/reorder', [FormFieldController::class, 'reorder']);

// Duplicate a field
Route::post('fields/{field}/duplicate', [FormFieldController::class, 'duplicate']);

use App\Http\Controllers\PublicFormController;

// List all open/active forms
Route::get('public/forms', [PublicFormController::class, 'index']);

// Show a single public form (with fields)
Route::get('public/forms/{form}', [PublicFormController::class, 'show']);

// Submit a public form
Route::post('public/forms/{form}/submit', [PublicFormController::class, 'submit']);

// Get a single submission for a form (public)
Route::get('public/forms/{form}/submissions/{submissionId}', [PublicFormController::class, 'getSubmission']);