<?php

// app/Services/DocumentService.php

namespace App\Services;

use App\Models\Document;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public function __construct(
        private string $disk = ''
    ) {
        // Prend config('documents.disk') ou 'public' par défaut
        $this->disk = $this->disk ?: (config('documents.disk', 'public') ?? 'public');
    }

    protected function fs(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    public function upload(array $payload, UploadedFile $file): Document
    {
        $hash = hash_file('sha256', $file->getRealPath());

        $existing = Document::where('hash', $hash)->orderByDesc('version')->first();
        $version = $existing ? ($existing->version + 1) : 1;

        $id = (string) Str::ulid();
        $dir = "documents/{$id}/v{$version}";
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext = strtolower($file->getClientOriginalExtension());
        $fileName = $ext ? "$safeName.$ext" : $safeName;

        // Stocke sur le disque choisi (public conseillé)
        $path = $this->fs()->putFileAs($dir, $file, $fileName);

        $doc = new Document();
        $doc->id = $id;
        $doc->fill([
            'champ1'      => $payload['champ1'] ?? null,
            'champ2'      => $payload['champ2'] ?? null,
            'champ3'      => $payload['champ3'] ?? null,
            'champ4'      => $payload['champ4'] ?? null,
            'commentaire' => $payload['commentaire'] ?? null,
            'file_name'   => $fileName,
            'file_path'   => $path,
            'file_size'   => $file->getSize(),
            'mime_type'   => $file->getClientMimeType(),
            'hash'        => $hash,
            'version'     => $version,
            'tags'        => $payload['tags'] ?? [],
            'metadata'    => $payload['metadata'] ?? [],
            'user_id'     => $payload['user_id'] ?? auth()->id(),
            'team_id'     => $payload['team_id'] ?? null,
        ]);
        $doc->save();

        return $doc;
    }

    public function delete(Document $document): void
    {
        Gate::authorize('delete', $document);
        $document->delete();
    }

    public function restore(Document $document): void
    {
        Gate::authorize('restore', $document);
        $document->restore();
    }

    public function forceDelete(Document $document): void
    {
        Gate::authorize('forceDelete', $document);
        $this->fs()->deleteDirectory(dirname($document->file_path));
        $document->forceDelete();
    }
}
