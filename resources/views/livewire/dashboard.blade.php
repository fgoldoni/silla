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
        $this->dispatch('notify', 'Document enregistré avec succès ✅');
        $this->selected = $doc;
        $this->resetPage();
    }

    public function select(string $id): void
    {
        $userId = auth()->id();

        $this->selected = Document::withTrashed()
            ->ownedBy($userId)
            ->findOrFail($id);
    }

    public function delete(string $id, DocumentService $service): void
    {
        $doc = Document::findOrFail($id);
        $service->delete($doc);

        if ($this->selected?->id === $id) $this->selected = null;

        $this->dispatch('notify', 'Document supprimé (corbeille) 🗑️');
        $this->resetPage();
    }

    public function restore(string $id, DocumentService $service): void
    {
        $doc = Document::onlyTrashed()->findOrFail($id);
        $service->restore($doc);

        $this->dispatch('notify', 'Document restauré ♻️');
        $this->resetPage();
    }

    public function forceDelete(string $id, DocumentService $service): void
    {
        $doc = Document::withTrashed()->findOrFail($id);
        $service->forceDelete($doc);

        if ($this->selected?->id === $id) $this->selected = null;

        $this->dispatch('notify', 'Document supprimé définitivement 🚨');
        $this->resetPage();
    }

    #[Computed]
    public function documents()
    {
        $allowed = ['active','trashed','all'];
        $scope = strtolower(trim((string) $this->scope));
        if (!in_array($scope, $allowed, true)) $scope = 'active';

        $userId = auth()->id();

        if ($scope === 'trashed')      $q = Document::onlyTrashed()->ownedBy($userId);
        elseif ($scope === 'all')      $q = Document::withTrashed()->ownedBy($userId);
        else                           $q = Document::query()->ownedBy($userId);

        if (!empty($this->search)) {
            $s = '%'.$this->search.'%';
            $q->where(function ($w) use ($s) {
                $w->where('champ1','like',$s)
                    ->orWhere('uid','like',$s)
                    ->orWhere('champ2','like',$s)
                    ->orWhere('champ3','like',$s)
                    ->orWhere('champ4','like',$s)
                    ->orWhere('file_name','like',$s);
            });
        }

        return $q->latest()->paginate(10);
    }
};
?>

<div class="p-4 sm:p-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Colonne gauche : Formulaire -->
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-lg p-4 sm:p-6">
            <form wire:submit.prevent="send" class="space-y-6">
                <flux:input wire:model.defer="champ1" label="Free Text" placeholder="Champ 1" clearable />

                <flux:select wire:model.defer="champ2" label="Drop-Downlist">
                    <option value="">Choose</option>
                    @foreach(\App\Models\Option::forChamp2()->get() as $opt)
                        <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.defer="champ3" label="Drop-Downlist">
                    <option value="">Choose</option>
                    @foreach(\App\Models\Option::forChamp3()->get() as $opt)
                        <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                    @endforeach
                </flux:select>

                <flux:textarea wire:model.defer="commentaire" label="Commentaire multiple line" placeholder="Commentaire" rows="4" />

                <flux:input wire:model.defer="champ4" label="Free Text" placeholder="Champ 4" clearable />

                <flux:input type="file" wire:model="file" label="FileToUpload" />

                <!-- indicateur d'upload côté UI (optionnel mais utile) -->
                <div class="text-xs text-zinc-500 mt-1" wire:loading wire:target="file">
                    Uploading... <flux:icon.loading class="inline size-4" />
                </div>

                <!-- Boutons -->
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
                        Clear
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
                        Send
                    </button>
                </div>
            </form>
        </div>

        <!-- Colonne droite : Liste + Détails -->
        <div class="space-y-6">
            <!-- Filtres -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-lg p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search..."
                        icon="magnifying-glass"
                        class="flex-1"
                    />
                    <flux:select
                        wire:model.live="scope"
                        class="w-full sm:w-auto"
                    >
                        <option value="active">Actifs</option>
                        <option value="trashed">Supprimés</option>
                        <option value="all">Tous</option>
                    </flux:select>
                </div>
            </div>

            <!-- Table (ID, UID, Champ1, Statut, Actions) -->
            <div class="overflow-x-auto rounded-2xl border dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-yellow-500 text-white">
                    <tr>
                        <th class="p-3 text-left">ID</th>
                        <th class="p-3 text-left">VERSION</th>
                        <th class="p-3 text-left">Champ1</th>
                        <th class="p-3 text-left">Statut</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($this->documents as $doc)
                        @php
                            $statusText = $doc->trashed() ? 'Supprimé' : 'Actif';
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
                                <span class="font-mono">version: {{ $doc->version }}</span>
                            </td>
                            <td class="p-3">
                                <button class="underline cursor-pointer" wire:click="select('{{ $doc->id }}')">
                                    {{ $doc->champ1 ?? '—' }}
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
                                                Download
                                            </a>
                                        @endif
                                    @endcan

                                    @can('delete', $doc)
                                        @if(!$doc->trashed())
                                            <button
                                                wire:click="delete('{{ $doc->id }}')"
                                                wire:confirm="Supprimer ce document ?"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                                <flux:icon.trash class="size-4" />
                                                <span class="sr-only sm:not-sr-only">Delete</span>
                                            </button>
                                        @endif
                                    @endcan

                                    @can('restore', $doc)
                                        @if($doc->trashed())
                                            <button
                                                wire:click="restore('{{ $doc->id }}')"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                                <flux:icon.arrow-path class="size-4" />
                                                <span class="sr-only sm:not-sr-only">Restore</span>
                                            </button>
                                        @endif
                                    @endcan

                                    @can('forceDelete', $doc)
                                        @if($doc->trashed())
                                            <button
                                                wire:click="forceDelete('{{ $doc->id }}')"
                                                wire:confirm="Suppression DÉFINITIVE ? Cette action est irréversible."
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md hover:bg-rose-50 dark:hover:bg-rose-900/30 text-rose-600 dark:text-rose-400">
                                                <flux:icon.x-mark class="size-4" />
                                                <span class="sr-only sm:not-sr-only">Force delete</span>
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-zinc-500 dark:text-zinc-400">
                                Aucun document trouvé
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>

                <div class="p-3">
                    {{ $this->documents->links() }}
                </div>
            </div>

            <!-- Détails -->
            <!-- Détails -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl border dark:border-zinc-700 p-6">
                @if($selected)
                    @php
                        $isTrashed = $selected->trashed();
                        $statusText = $isTrashed ? 'Supprimé' : 'Actif';
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
                        <flux:heading size="md">Détails</flux:heading>

                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $statusClasses }}">
                {{ $statusText }}
            </span>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <!-- Identifiants -->
                        <div class="sm:col-span-1">
                            <dt class="opacity-60">UID (numérique)</dt>
                            <dd class="mt-1">
                                <flux:input value="{{ $selected->uid }}" readonly copyable class="font-mono" />
                            </dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="opacity-60">ULID</dt>
                            <dd class="mt-1">
                                <flux:input value="{{ $selected->id }}" readonly copyable class="font-mono" />
                            </dd>
                        </div>

                        <!-- Champs fonctionnels -->
                        <div>
                            <dt class="opacity-60">Champ 1</dt>
                            <dd class="mt-1">{{ $selected->champ1 ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">Champ 2</dt>
                            <dd class="mt-1">{{ $selected->champ2 ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">Champ 3</dt>
                            <dd class="mt-1">{{ $selected->champ3 ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">Champ 4</dt>
                            <dd class="mt-1">{{ $selected->champ4 ?? '—' }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="opacity-60">Commentaire</dt>
                            <dd class="mt-1 text-zinc-700 dark:text-zinc-200 whitespace-pre-line">
                                {{ $selected->commentaire ?: '—' }}
                            </dd>
                        </div>

                        <!-- Fichier -->
                        <div>
                            <dt class="opacity-60">Nom du fichier</dt>
                            <dd class="mt-1">{{ $selected->file_name }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">Taille</dt>
                            <dd class="mt-1">{{ $humanSize }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">MIME</dt>
                            <dd class="mt-1">{{ $selected->mime_type }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">Version</dt>
                            <dd class="mt-1">v{{ $selected->version }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="opacity-60">Hash (SHA-256)</dt>
                            <dd class="mt-1">
                                <flux:input value="{{ $selected->hash }}" readonly copyable class="font-mono" />
                            </dd>
                        </div>

                        <!-- Liens -->
                        <div class="sm:col-span-2">
                            <dt class="opacity-60">Lien de téléchargement sécurisé (signé)</dt>
                            <dd class="mt-1">
                                <flux:input icon="key" value="{{ $signedUrl }}" readonly copyable class="font-mono" />
                            </dd>
                        </div>

                        @can('download', $selected)
                            @unless($isTrashed)
                                <div class="sm:col-span-2">
                                    <dt class="opacity-60">Lien direct (disque)</dt>
                                    <dd class="mt-1 flex flex-col sm:flex-row sm:items-center gap-2">
                                        <flux:input value="{{ $directUrl }}" readonly copyable class="font-mono" />
                                        <a href="{{ $directUrl }}" target="_blank" class="underline text-yellow-700 dark:text-yellow-400">
                                            Ouvrir
                                        </a>
                                    </dd>
                                </div>
                            @endunless
                        @endcan

                        <!-- Tags & Metadata -->
                        <div class="sm:col-span-2">
                            <dt class="opacity-60">Tags</dt>
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
                            <dt class="opacity-60">Metadata</dt>
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

                        <!-- Propriétés relationnelles -->
                        <div>
                            <dt class="opacity-60">Utilisateur</dt>
                            <dd class="mt-1">{{ $selected->user_id ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">Équipe</dt>
                            <dd class="mt-1">{{ $selected->team_id ?? '—' }}</dd>
                        </div>

                        <!-- Timestamps -->
                        <div>
                            <dt class="opacity-60">Créé le</dt>
                            <dd class="mt-1">
                                {{ optional($selected->created_at)->format('Y-m-d H:i') ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="opacity-60">Modifié le</dt>
                            <dd class="mt-1">
                                {{ optional($selected->updated_at)->format('Y-m-d H:i') ?? '—' }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="opacity-60">Supprimé le</dt>
                            <dd class="mt-1">
                                {{ optional($selected->deleted_at)->format('Y-m-d H:i') ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="text-zinc-500 dark:text-zinc-400">Sélectionne un élément du tableau pour afficher ses détails ici.</p>
                @endif
            </div>


        </div>
    </div>
</div>
