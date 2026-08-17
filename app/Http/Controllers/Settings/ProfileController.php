<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($this->attributes($request));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('profile.edit')->with('success', 'Perfil atualizado.');
    }

    /**
     * Resolve os campos graváveis, tratando a foto à parte: `photo` e
     * `remove_photo` são entradas do formulário, não colunas.
     *
     * @return array<string, mixed>
     */
    private function attributes(ProfileUpdateRequest $request): array
    {
        $attributes = $request->validated();
        unset($attributes['photo'], $attributes['remove_photo']);

        $replacing = $request->hasFile('photo');
        $removing = $request->boolean('remove_photo');

        if (! $replacing && ! $removing) {
            return $attributes;
        }

        // Trocar ou remover sempre apaga o arquivo antigo, para o disco não
        // acumular imagens órfãs.
        if ($request->user()->photo_path) {
            Storage::disk('public')->delete($request->user()->photo_path);
        }

        $attributes['photo_path'] = $replacing
            ? $request->file('photo')->store('users', 'public')
            : null;

        return $attributes;
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        // A conta sai, o arquivo também — senão fica órfão no disco para sempre.
        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
