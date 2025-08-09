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
    public string $scope = 'active';
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
        $this->selected = Document::withTrashed()->find($id);
    }

    #[Computed]
    public function documents()
    {
        $allowed = ['active','trashed','all'];
        $scope = strtolower(trim((string) $this->scope));
        if (!in_array($scope, $allowed, true)) $scope = 'active';

        if ($scope === 'trashed')      $q = Document::onlyTrashed();
        elseif ($scope === 'all')      $q = Document::withTrashed();
        else                           $q = Document::query();

        if (!empty($this->search)) {
            $s = '%'.$this->search.'%';
            $q->where(function ($w) use ($s) {
                $w->where('champ1','like',$s)
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
                    <option value="00">00</option>
                    <option value="88">88</option>
                    <option value="99">99</option>
                </flux:select>

                <flux:select wire:model.defer="champ3" label="Drop-Downlist">
                    <option value="">Choose</option>
                    <option value="00">00</option>
                    <option value="88">88</option>
                    <option value="99">99</option>
                </flux:select>

                <flux:textarea wire:model.defer="commentaire" label="Commentaire multiple line" placeholder="Commentaire" rows="4" />

                <flux:input wire:model.defer="champ4" label="Free Text" placeholder="Champ 4" clearable />

                <!-- Bloc upload -->
                <flux:field>
                    <flux:input type="file" wire:model="file" label="FileToUpload" />
                    <flux:error name="file" />
                </flux:field>

                <!-- Boutons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="button"
                            wire:click="clear"
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 px-4 py-2 text-sm font-medium transition">
                        <flux:icon.x-mark class="size-5" />
                        Clear
                    </button>

                    <flux:button type="submit" variant="primary" icon="arrow-up-tray" class="flex-1">
                        Send
                    </flux:button>
                </div>
            </form>
        </div>

        <!-- Colonne droite : Liste et détails -->
        <div class="space-y-6">
            <!-- Filtres -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-lg p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    <flux:input wire:model.debounce.500ms="search" placeholder="Search..." icon="magnifying-glass" class="flex-1" />
                    <flux:select wire:model="scope" class="w-full sm:w-auto">
                        <option value="active">Actifs</option>
                        <option value="trashed">Supprimés</option>
                        <option value="all">Tous</option>
                    </flux:select>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-2xl border dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-yellow-500 text-white">
                    <tr>
                        <th class="p-3 text-left">Champ1</th>
                        <th class="p-3 text-center">Ch2</th>
                        <th class="p-3 text-center">Ch3</th>
                        <th class="p-3 text-left">Champ4</th>
                        <th class="p-3 text-left">FileName</th>
                        <th class="p-3"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($this->documents as $doc)
                        <tr class="odd:bg-zinc-50 dark:odd:bg-zinc-800/60 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition"
                            wire:click="select('{{ $doc->id }}')" role="button">
                            <td class="p-3">{{ $doc->champ1 }}</td>
                            <td class="p-3 text-center">{{ $doc->champ2 }}</td>
                            <td class="p-3 text-center">{{ $doc->champ3 }}</td>
                            <td class="p-3">{{ $doc->champ4 }}</td>
                            <td class="p-3">{{ $doc->file_name }}</td>
                            <td class="p-3 text-right">
                                @if(!$doc->trashed())
                                    <a class="underline text-yellow-700 dark:text-yellow-400"
                                       href="{{ Storage::disk(config('documents.disk','public'))->url($doc->file_path) }}"
                                       target="_blank">
                                        Download-Link
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-zinc-500 dark:text-zinc-400">
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
            <div class="bg-white dark:bg-zinc-900 rounded-2xl border dark:border-zinc-700 p-6">
                @if($selected)
                    <flux:heading size="md" class="mb-4">Details</flux:heading>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="opacity-60">Drop-Downlist (Format)</dt>
                            <dd>
                                @php
                                    $labels = ['00' => 'Category00','88' => 'Category88','99' => 'Category99'];
                                @endphp
                                {{ $selected->champ2 ? ($selected->champ2.' - '.($labels[$selected->champ2] ?? '')) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="opacity-60">Nom</dt>
                            <dd>{{ $selected->file_name }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">Taille</dt>
                            <dd>{{ number_format($selected->file_size/1024, 1) }} KB</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">MIME</dt>
                            <dd>{{ $selected->mime_type }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-60">Version</dt>
                            <dd>v{{ $selected->version }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-zinc-500 dark:text-zinc-400">Select on element in the Table, show details here</p>
                @endif
            </div>
        </div>
    </div>
</div>
