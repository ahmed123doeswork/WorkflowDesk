<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Enquiry;
use App\Models\User;

class EnquiryPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Counsellor, Role::Viewer], true);
    }

    public function view(User $user, Enquiry $enquiry): bool
    {
        return $user->tenant_id === $enquiry->tenant_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Counsellor], true);
    }

    public function update(User $user, Enquiry $enquiry): bool
    {
        return $user->tenant_id === $enquiry->tenant_id
            && in_array($user->role, [Role::Admin, Role::Counsellor], true);
    }

    public function assign(User $user, Enquiry $enquiry): bool
    {
        return $this->update($user, $enquiry);
    }

    public function transition(User $user, Enquiry $enquiry): bool
    {
        return $this->update($user, $enquiry);
    }
}
