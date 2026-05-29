<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        abort_if($this->administratorExists(), 403);

        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        abort_if($this->administratorExists(), 403);

        $request->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['nullable', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:45', 'unique:'.User::class],
            'username' => ['nullable', 'string', 'max:255', 'unique:'.User::class],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'surname' => $request->surname,
            'phone' => $request->phone,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
            $role = Role::query()
                ->where('role_name->fr', 'Administrateur')
                ->orWhere('role_name->en', 'Administrator')
                ->first();

            $role ??= Role::create([
                'role_name' => [
                    'fr' => 'Administrateur',
                    'en' => 'Administrator',
                    'ln' => 'Mokambi',
                ],
                'role_description' => [
                    'fr' => 'Gestion des données de fonctionnement de la plateforme',
                    'en' => 'Management of platform operating data',
                    'ln' => 'Bokambami ya ba données ya mosala ya plateforme',
                ],
            ]);

            DB::table('role_user')->updateOrInsert(
                ['role_id' => $role->id, 'user_id' => $user->id],
                ['is_selected' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    private function administratorExists(): bool
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_user')) {
            return false;
        }

        return Role::query()
            ->where('role_name->fr', 'Administrateur')
            ->whereHas('users', fn ($query) => $query->where('role_user.is_selected', true))
            ->exists();
    }
}
