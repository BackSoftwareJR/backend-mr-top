<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Enums\UserType;
use App\Models\EditorialContent;
use App\Models\User;

class EditorialContentPolicy
{
    public function accessAdmin(User $user): bool
    {
        if ($user->user_type === UserType::Superadmin || $this->isAdmin($user)) {
            return true;
        }

        if ($this->isStructureAuthor($user)) {
            return false;
        }

        return $user->hasPermission('editorial.view')
            || $user->hasPermission('editorial.create')
            || $user->hasPermission('editorial.edit')
            || $user->hasPermission('editorial.publish')
            || $user->hasPermission('editorial.moderate')
            || $user->hasPermission('editorial.seo.approve')
            || $user->hasPermission('editorial.index.manage');
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user)
            || $user->hasPermission('editorial.view')
            || $this->isStructureAuthor($user);
    }

    public function view(User $user, EditorialContent $content): bool
    {
        if ($this->isAdmin($user) || $user->hasPermission('editorial.view')) {
            return true;
        }

        return $this->partnerOwnsContent($user, $content);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user)
            || $user->hasPermission('editorial.create')
            || $this->isStructureAuthor($user);
    }

    public function update(User $user, EditorialContent $content): bool
    {
        if ($this->isAdmin($user) || $user->hasPermission('editorial.edit')) {
            return true;
        }

        return $this->partnerOwnsDraft($user, $content);
    }

    public function delete(User $user, EditorialContent $content): bool
    {
        return $this->isAdmin($user) || $user->hasPermission('editorial.publish');
    }

    public function restore(User $user, EditorialContent $content): bool
    {
        return $this->delete($user, $content);
    }

    public function forceDelete(User $user, EditorialContent $content): bool
    {
        return $this->isAdmin($user);
    }

    public function publish(User $user, EditorialContent $content): bool
    {
        return $this->isAdmin($user) || $user->hasPermission('editorial.publish');
    }

    public function moderate(User $user, EditorialContent $content): bool
    {
        return $this->isAdmin($user) || $user->hasPermission('editorial.moderate');
    }

    public function approveSeo(User $user, EditorialContent $content): bool
    {
        return $this->isAdmin($user) || $user->hasPermission('editorial.seo.approve');
    }

    public function manageIndex(User $user): bool
    {
        return $this->isAdmin($user) || $user->hasPermission('editorial.index.manage');
    }

    private function isAdmin(User $user): bool
    {
        if ($user->user_type === UserType::Superadmin) {
            return true;
        }

        return $user->roles()->whereIn('name', ['superadmin', 'super_admin', 'chief_editor'])->exists();
    }

    private function isStructureAuthor(User $user): bool
    {
        if ($user->user_type === UserType::B2b) {
            return true;
        }

        return $user->roles()->whereIn('name', [
            'partner',
            'partner_owner',
            'partner_staff',
            'structure_author',
        ])->exists();
    }

    private function partnerOwnsContent(User $user, EditorialContent $content): bool
    {
        if (! $this->isStructureAuthor($user)) {
            return false;
        }

        $companyId = $user->companies()->value('companies.id');

        return $companyId !== null
            && $content->company_id === $companyId
            && $content->author_type === EditorialAuthorType::Company;
    }

    private function partnerOwnsDraft(User $user, EditorialContent $content): bool
    {
        return $this->partnerOwnsContent($user, $content)
            && $content->status === EditorialContentStatus::Draft;
    }
}
