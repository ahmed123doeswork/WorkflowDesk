<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditChain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        return User::query()->paginate(
            min((int) $request->integer('per_page', 15), 100)
        );
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        return $user;
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'in:admin,counsellor,viewer'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->update($validated);

            AuditChain::record('user.updated', $user, AuditChain::diff($user, array_keys($validated)));
        });

        return $user;
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        DB::transaction(function () use ($user) {
            AuditChain::record('user.deleted', $user, [
                'before' => $user->only(['name', 'email', 'role']),
            ]);

            $user->delete();
        });

        return response()->json(status: 204);
    }
}
