<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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

        $user->update($validated);

        return $user;
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(status: 204);
    }
}
