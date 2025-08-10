<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        return $document->user_id === $user->id;
    }
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
        return false;
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return false;
    }

    public function download(User $user, Document $document): bool
    {
        return true; // ou restreindre par visibilité
    }
}
