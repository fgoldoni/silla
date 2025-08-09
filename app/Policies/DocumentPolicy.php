<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Document $document): bool { return true; }
    public function create(User $user): bool { return true; }

    public function update(User $user, Document $document): bool
    {
        return $document->user_id === $user->id; // ajuster selon rôles
    }

    public function delete(User $user, Document $document): bool
    {
        return $document->user_id === $user->id;
    }

    public function restore(User $user, Document $document): bool
    {
        return $document->user_id === $user->id;
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return $document->user_id === $user->id; // restreindre si besoin
    }

    public function download(User $user, Document $document): bool
    {
        return true; // ou restreindre par visibilité
    }
}
