<?php

use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination, WithFileUploads;

    public ?string $champ1 = null;
    public ?string $champ2 = null;
    public ?string $champ3 = null;
    public ?string $champ4 = null;
    public ?string $commentaire = null;

    /** @var \Illuminate\Http\UploadedFile|null */
    public $file;

    public ?string $search = null;
    public string $scope = 'active'; // active|trashed|all
    public ?Document $selected = null;

    protected $rules = [
        'champ1'      => 'nullable|string|max:255',
        'champ2'      => 'nullable|string|max:255',
        'champ3'      => 'nullable|string|max:255',
        'champ4'      => 'nullable|string|max:255',
        'commentaire' => 'nullable|string',
        'file'        => 'required|file|max:5120',
    ];

    public function clear(): void
    {
        $this->reset(['champ1','champ2','champ3','champ4','commentaire','file']);
        $this->resetErrorBag();
    }

    public function send(DocumentService $service): void
    {
        $this->validate();

        $doc = $service->upload([
            'champ1'      => $this->champ1,
            'champ2'      => $this->champ2,
            'champ3'      => $this->champ3,
            'champ4'      => $this->champ4,
            'commentaire' => $this->commentaire,
        ], $this->file);

        $this->clear();
        $this->dispatch('notify', __('Document saved successfully ✅'));
        $this->selected = $doc;
        $this->resetPage();
    }

    public function select(string $id): void
    {
        $viewer = auth()->user();
        $isSuperAdmin = $viewer && (method_exists($viewer, 'isSuperAdmin')
                ? (bool) $viewer->isSuperAdmin()
                : (bool) ($viewer->isSuperAdmin ?? false));

        $q = Document::withTrashed();
        if (!$isSuperAdmin) {
            $q->ownedBy($viewer?->id);
        }

        $this->selected = $q->findOrFail($id);
    }

    public function delete(string $id, DocumentService $service): void
    {
        $doc = Document::findOrFail($id);
        $service->delete($doc);

        if ($this->selected?->id === $id) $this->selected = null;

        $this->dispatch('notify', __('Document moved to trash 🗑️'));
        $this->resetPage();
    }

    public function restore(string $id, DocumentService $service): void
    {
        $doc = Document::onlyTrashed()->findOrFail($id);
        $service->restore($doc);

        $this->dispatch('notify', __('Document restored ♻️'));
        $this->resetPage();
    }

    public function forceDelete(string $id, DocumentService $service): void
    {
        $doc = Document::withTrashed()->findOrFail($id);
        $service->forceDelete($doc);

        if ($this->selected?->id === $id) $this->selected = null;

        $this->dispatch('notify', __('Document permanently deleted 🚨'));
        $this->resetPage();
    }

    #[Computed]
    public function documents()
    {
        $allowed = ['active','trashed','all'];
        $scope = strtolower(trim((string) $this->scope));
        if (!in_array($scope, $allowed, true)) $scope = 'active';

        $viewer = auth()->user();
        $isSuperAdmin = $viewer && (method_exists($viewer, 'isSuperAdmin')
                ? (bool) $viewer->isSuperAdmin()
                : (bool) ($viewer->isSuperAdmin ?? false));

        // Base query selon le scope
        if ($scope === 'trashed')      $q = Document::onlyTrashed();
        elseif ($scope === 'all')      $q = Document::withTrashed();
        else                           $q = Document::query();

        // Accès : super admin = tout, sinon seulement ses docs
        if (!$isSuperAdmin) {
            $q->ownedBy($viewer?->id);
        }

        // Eager-load de l'utilisateur pour l'affichage de son nom
        $q->with('user');

        // Recherche élargie (champs + utilisateur)
        if (filled($this->search)) {
            $term    = trim($this->search);
            $sLike   = '%'.$term.'%';                     // pour uid / valeurs non textuelles
            $sLower  = '%'.mb_strtolower($term).'%';      // pour comparaisons insensibles à la casse

            $q->where(function ($w) use ($sLike, $sLower) {
                $w->whereRaw('LOWER(champ1) LIKE ?', [$sLower])
                    ->orWhere('uid', 'like', $sLike)
                    ->orWhereRaw('LOWER(champ2) LIKE ?', [$sLower])
                    ->orWhereRaw('LOWER(champ3) LIKE ?', [$sLower])
                    ->orWhereRaw('LOWER(champ4) LIKE ?', [$sLower])
                    ->orWhereRaw('LOWER(file_name) LIKE ?', [$sLower])
                    ->orWhereHas('user', function ($u) use ($sLower) {
                        $u->whereRaw('LOWER(name) LIKE ?', [$sLower])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$sLower]);
                    });
            });
        }

        return $q->latest()->paginate(10);
    }
};
?>

<div class="p-4 sm:p-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left column: Form -->
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-lg p-4 sm:p-6">
            <form wire:submit.prevent="send" class="space-y-6">
                <flux:input
                    wire:model.defer="champ1"
                    label="{{ __('Free text') }}"
                    placeholder="{{ __('Field 1') }}"
                    clearable
                />

                <flux:select wire:model.defer="champ2" label="{{ __('Drop-down list') }}">
                    <option value="">{{ __('Choose') }}</option>
                    @foreach(\App\Models\Option::forChamp2()->get() as $opt)
                        <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.defer="champ3" label="{{ __('Drop-down list') }}">
                    <option value="">{{ __('Choose') }}</option>
                    @foreach(\App\Models\Option::forChamp3()->get() as $opt)
                        <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                    @endforeach
                </flux:select>

                <flux:textarea
                    wire:model.defer="commentaire"
                    label="{{ __('Multi-line comment') }}"
                    placeholder="{{ __('Comment') }}"
                    rows="4"
                />

                <flux:input
                    wire:model.defer="champ4"
                    label="{{ __('Free text') }}"
                    placeholder="{{ __('Field 4') }}"
                    clearable
                />

                <flux:input type="file" wire:model="file" label="{{ __('File to upload') }}" />

                <!-- upload indicator -->
                <div class="text-xs text-zinc-500 mt-1" wire:loading wire:target="file">
                    {{ __('Uploading...') }} <flux:icon.loading class="inline size-4" />
                </div>

                <!-- Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <!-- Clear -->
                    <button type="button"
                            wire:click="clear"
                            wire:loading.attr="disabled"
                            wire:target="clear"
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 px-4 py-2 text-sm font-medium transition">
                        <span wire:loading.remove wire:target="clear">
                            <flux:icon.x-mark class="size-5" />
                        </span>
                        <span wire:loading wire:target="clear">
                            <flux:icon.loading class="size-5" />
                        </span>
                        {{ __('Clear') }}
                    </button>

                    <!-- Send -->
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="file,send"
                            class="flex-1 inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 text-sm font-medium transition">
                        <span wire:loading.remove wire:target="send">
                            <flux:icon.arrow-up-tray class="size-5" />
                        </span>
                        <span wire:loading wire:target="send">
                            <flux:icon.loading class="size-5" />
                        </span>
                        {{ __('Send') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Right column: List + Details -->
        <div class="space-y-6">
            <!-- Filters -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-lg p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Search…') }}"
                        icon="magnifying-glass"
                        class="flex-1
                    " />
                    <flux:select
                        wire:model.live="scope"
                        class="w-full sm:w-auto"
                    >
                        <option value="active">{{ __('Active') }}</option>
                        <option value="trashed">{{ __('Deleted') }}</option>
                        <option value="all">{{ __('All') }}</option>
                    </flux:select>
                </div>
            </div>

            <!-- Table (ID, UID, Field1, User, Status, Actions) -->
            <div class="overflow-x-auto rounded-2xl border dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-yellow-500 text-white">
                    <tr>
                        <th class="p-3 text-left">{{ __('ID') }}</th>
                        <th class="p-3 text-left">{{ __('Version') }}</th>
                        <th class="p-3 text-left">{{ __('Field 1') }}</th>
                        <th class="p-3 text-left">{{ __('User') }}</th>
                        <th class="p-3 text-left">{{ __('Status') }}</th>
                        <th class="p-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($this->documents as $doc)
                        @php
                            $statusText = $doc->trashed() ? __('Deleted') : __('Active');
                            $statusClasses = $doc->trashed()
                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'
                                : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';
                        @endphp
                        <tr class="odd:bg-zinc-50 dark:odd:bg-zinc-800/60 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                            <td class="p-3">
                                <button class="underline cursor-pointer" wire:click="select('{{ $doc->id }}')">
                                    {{ $doc->uid }}
                                </button>
                            </td>
                            <td class="p-3">
                                <span class="font-mono">{{ 'v:' . $doc->version }}</span>
                            </td>
                            <td class="p-3">
                                <button class="underline cursor-pointer" wire:click="select('{{ $doc->id }}')">
                                    {{ $doc->champ1 ?? '—' }}
                                </button>
                            </td>
                            <td class="p-3">
                                <button class="underline cursor-pointer" wire:click="select('{{ $doc->id }}')">
                                    <span class="truncate">{{ $doc->user?->name ?? '—' }}({{ $doc->user?->email ?? '—' }})</span>
                                </button>
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $statusClasses }}">
                                    {{ $statusText }}
                                </span>
                            </td>
                            <td class="p-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    @can('download', $doc)
                                        @if(!$doc->trashed())
                                            <a class="underline text-yellow-700 dark:text-yellow-400"
                                               href="{{ Storage::disk(config('documents.disk', config('filesystems.default')))->url($doc->file_path) }}"
                                               target="_blank">
                                                {{ __('Download') }}
                                            </a>
                                        @endif
                                    @endcan

                                    @can('delete', $doc)
                                        @if(!$doc->trashed())
                                            <button
                                                wire:click="delete('{{ $doc->id }}')"
                                                wire:confirm="{{ __('Delete this document?') }}"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                                <flux:icon.trash class="size-4" />
                                                <span class="sr-only sm:not-sr-only">{{ __('Delete') }}</span>
                                            </button>
                                        @endif
                                    @endcan

                                    @can('restore', $doc)
                                        @if($doc->trashed())
                                            <button
                                                wire:click="restore('{{ $doc->id }}')"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                                <flux:icon.arrow-path class="size-4" />
                                                <span class="sr-only sm:not-sr-only">{{ __('Restore') }}</span>
                                            </button>
                                        @endif
                                    @endcan

                                    @can('forceDelete', $doc)
                                        @if($doc->trashed())
                                            <button
                                                wire:click="forceDelete('{{ $doc->id }}')"
                                                wire:confirm="{{ __('PERMANENT deletion? This action is irreversible.') }}"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md hover:bg-rose-50 dark:hover:bg-rose-900/30 text-rose-600 dark:text-rose-400">
                                                <flux:icon.x-mark class="size-4" />
                                                <span class="sr-only sm:not-sr-only">{{ __('Force delete') }}</span>
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No documents found') }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>

                <div class="p-3">
                    {{ $this->documents->links() }}
                </div>
            </div>

            <!-- Details -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl border dark:border-zinc-700 p-6">
                @if($selected)
                    @php
                        $isTrashed = $selected->trashed();
                        $statusText = $isTrashed ? __('Deleted') : __('Active');
                        $statusClasses = $isTrashed
                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'
                            : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300';

                        $humanSize = $selected->file_size >= 1048576
                            ? number_format($selected->file_size/1048576, 2).' MB'
                            : number_format(max(1, $selected->file_size)/1024, 1).' KB';

                        $signedUrl = URL::signedRoute('documents.download', ['document' => $selected->id]);
                        $directUrl = Storage::disk(config('documents.disk', config('filesystems.default')))
                            ->url($selected->file_path);

                        $tags = $selected->tags ?? [];
                        $metadata = $selected->metadata ?? [];
                    @endphp

                    <div class="flex items-start justify-between gap-3 mb-4">
                        <flux:heading size="md">{{ __('Details') }}</flux:heading>

                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $statusClasses }}">
                            {{ $statusText }}
                        </span>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <!-- Identifiers -->
                        <div class="sm:col-span-1">
                            <dt class="opacity-60">{{ __('UID (numeric)') }}</dt>
                            <dd class="mt-1">
                                <flux:input value="{{ $selected->uid }}" readonly copyable class="font-mono" />
                            </dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="opacity-60">{{ __('ULID') }}</dt>
                            <dd class="mt-1">
                                <flux:input value="{{ $selected->id }}" readonly copyable class="font-mono" />
                            </dd>
                        </div>

                        <!-- Functional fields -->
                        <div>
                            <dt class="opacity-60">{{ __('Field 1') }}</dt>
                            <dd class="mt-1">{{ $selected->champ1 ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">{{ __('Field 2') }}</dt>
                            <dd class="mt-1">{{ $selected->champ2 ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">{{ __('Field 3') }}</dt>
                            <dd class="mt-1">{{ $selected->champ3 ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">{{ __('Field 4') }}</dt>
                            <dd class="mt-1">{{ $selected->champ4 ?? '—' }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="opacity-60">{{ __('Comment') }}</dt>
                            <dd class="mt-1 text-zinc-700 dark:text-zinc-200 whitespace-pre-line">
                                {{ $selected->commentaire ?: '—' }}
                            </dd>
                        </div>

                        <!-- File -->
                        <div>
                            <dt class="opacity-60">{{ __('File name') }}</dt>
                            <dd class="mt-1">{{ $selected->file_name }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">{{ __('Size') }}</dt>
                            <dd class="mt-1">{{ $humanSize }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">{{ __('MIME') }}</dt>
                            <dd class="mt-1">{{ $selected->mime_type }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">{{ __('Version') }}</dt>
                            <dd class="mt-1">v{{ $selected->version }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="opacity-60">{{ __('Hash (SHA-256)') }}</dt>
                            <dd class="mt-1">
                                <flux:input value="{{ $selected->hash }}" readonly copyable class="font-mono" />
                            </dd>
                        </div>

                        <!-- Links -->
                        <div class="sm:col-span-2">
                            <dt class="opacity-60">{{ __('Secure download link (signed)') }}</dt>
                            <dd class="mt-1">
                                <flux:input icon="key" value="{{ $signedUrl }}" readonly copyable class="font-mono" />
                            </dd>
                        </div>

                        @can('download', $selected)
                            @unless($isTrashed)
                                <div class="sm:col-span-2">
                                    <dt class="opacity-60">{{ __('Direct link (disk)') }}</dt>
                                    <dd class="mt-1 flex flex-col sm:flex-row sm:items-center gap-2">
                                        <flux:input value="{{ $directUrl }}" readonly copyable class="font-mono" />
                                        <a href="{{ $directUrl }}" target="_blank" class="underline text-yellow-700 dark:text-yellow-400">
                                            {{ __('Open') }}
                                        </a>
                                    </dd>
                                </div>
                            @endunless
                        @endcan

                        <!-- Tags & Metadata -->
                        <div class="sm:col-span-2">
                            <dt class="opacity-60">{{ __('Tags') }}</dt>
                            <dd class="mt-1">
                                @if(!empty($tags))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($tags as $tag)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs bg-zinc-100 dark:bg-zinc-800">
                                                {{ is_string($tag) ? $tag : json_encode($tag, JSON_UNESCAPED_UNICODE) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="opacity-60">{{ __('Metadata') }}</dt>
                            <dd class="mt-1">
                                @if(!empty($metadata))
                                    <pre class="max-h-48 overflow-auto rounded-lg bg-zinc-50 dark:bg-zinc-800 p-3 text-xs font-mono">
{{ json_encode($metadata, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}
                                    </pre>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>

                        <!-- Relations -->
                        <div>
                            <dt class="opacity-60">{{ __('User') }}</dt>
                            <dd class="mt-1">{{ $selected->user?->name ?? '—' }} <span class="opacity-60">(#{{ $selected->user_id ?? '—' }})</span></dd>
                        </div>
                        <div>
                            <dt class="opacity-60">{{ __('Team') }}</dt>
                            <dd class="mt-1">{{ $selected->team_id ?? '—' }}</dd>
                        </div>

                        <!-- Timestamps -->
                        <div>
                            <dt class="opacity-60">{{ __('Created at') }}</dt>
                            <dd class="mt-1">
                                {{ optional($selected->created_at)->format('Y-m-d H:i') ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="opacity-60">{{ __('Updated at') }}</dt>
                            <dd class="mt-1">
                                {{ optional($selected->updated_at)->format('Y-m-d H:i') ?? '—' }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="opacity-60">{{ __('Deleted at') }}</dt>
                            <dd class="mt-1">
                                {{ optional($selected->deleted_at)->format('Y-m-d H:i') ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Select an item from the table to view its details here.') }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
