<?php

use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Volt::route('/dashboard', 'dashboard')->name('dashboard');
    Volt::route('/documents', 'documents.index')->name('documents.index');
    Route::get('/documents/download/{document}', function (Request $request, Document $document) {
        Gate::authorize('download', $document);

        return Storage::disk(config('documents.disk'))
            ->download($document->file_path, $document->file_name);
    })->name('documents.download')->middleware(['signed']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';



