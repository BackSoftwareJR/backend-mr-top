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
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isPartner($user);
    }

    public function view(User $user, EditorialContent $content): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->partnerOwnsContent($user, $content);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $this->isPartner($user);
    }

    public function update(User $user, EditorialContent $content): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->partnerOwnsDraft($user, $content);
    }

    public function delete(User $user, EditorialContent $content): bool
    {
        return $this->isAdmin($user);
    }

    public function restore(User $user, EditorialContent $content): bool
    {
        return $this->isAdmin($user);
    }

    public function forceDelete(User $user, EditorialContent $content): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        if ($user->user_type === UserType::Superadmin) {
            return true;
        }

        return $user->roles()->whereIn('name', ['superadmin', 'super_admin'])->exists();
    }

    private function isPartner(User $user): bool
    {
        if ($user->user_type === UserType::B2b) {
            return true;
        }

        return $user->roles()->whereIn('name', ['partner', 'partner_owner', 'partner_staff'])->exists();
    }

    private function partnerOwnsContent(User $user, EditorialContent $content): bool
    {
        if (! $this->isPartner($user)) {
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
