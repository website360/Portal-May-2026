<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UserRequest;
use App\Models\User;
use App\Support\ListSorting;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /** @var array<string, string|callable> */
    private const SORTS = [
        'name' => 'name',
        'email' => 'email',
        'role' => [self::class, 'orderByRole'],
        'created_at' => 'created_at',
    ];

    public function index(Request $request): Response
    {
        $sorting = ListSorting::resolve($request, self::SORTS, 'name');

        $query = User::query()->withCount('tasks');

        ListSorting::apply($query, self::SORTS, $sorting['sort'], $sorting['direction']);

        return Inertia::render('configuracoes/usuarios', [
            'filters' => $sorting,
            'users' => $query->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $user->role,
                'permissions' => $user->permissionMap(),
                'tasks_count' => $user->tasks_count,
                'is_me' => $user->id === $request->user()->id,
            ]),
            'modules' => Permissions::MODULES,
            'levels' => Permissions::LEVELS,
        ]);
    }

    /** Administradores primeiro; dentro de cada grupo, ordem alfabética. */
    public static function orderByRole($query, string $direction): void
    {
        $query->orderByRaw("case role when '".User::ROLE_ADMIN."' then 0 else 1 end ".($direction === 'desc' ? 'desc' : 'asc'))
            ->orderBy('name');
    }

    public function store(UserRequest $request): RedirectResponse
    {
        User::create($this->attributes($request));

        return back()->with('success', 'Usuário criado.');
    }

    public function update(UserRequest $request, User $usuario): RedirectResponse
    {
        if ($error = $this->wouldLoseTheLastAdmin($request, $usuario)) {
            return back()->withErrors(['role' => $error]);
        }

        $attributes = $this->attributes($request);

        // Senha em branco na edição significa "manter a atual".
        if ($attributes['password'] === null) {
            unset($attributes['password']);
        }

        $usuario->update($attributes);

        return back()->with('success', 'Usuário atualizado.');
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->withErrors(['usuario' => 'Você não pode excluir a própria conta por aqui.']);
        }

        if ($usuario->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() === 1) {
            return back()->withErrors(['usuario' => 'Este é o último administrador. Promova outra pessoa antes de excluí-lo.']);
        }

        $name = $usuario->name;

        // As tarefas ficam, sem responsável (a coluna é anulável) — histórico de
        // trabalho não some junto com quem saiu da agência.
        if ($usuario->photo_path) {
            Storage::disk('public')->delete($usuario->photo_path);
        }

        $usuario->delete();

        return back()->with('success', "Usuário {$name} excluído.");
    }

    /**
     * Rebaixar o último administrador deixaria o sistema sem ninguém capaz de
     * editar permissões — inclusive de desfazer o próprio rebaixamento.
     */
    private function wouldLoseTheLastAdmin(UserRequest $request, User $usuario): ?string
    {
        $demoting = $usuario->isAdmin() && $request->validated()['role'] !== User::ROLE_ADMIN;

        if (! $demoting) {
            return null;
        }

        if (User::where('role', User::ROLE_ADMIN)->count() > 1) {
            return null;
        }

        return 'Este é o último administrador. Promova outra pessoa antes de rebaixá-lo.';
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(UserRequest $request): array
    {
        $data = $request->validated();

        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
            'role' => $data['role'],
            // Administrador não guarda mapa: ele passa por cima de qualquer um.
            // Gravar um mapa cheio criaria duas fontes da mesma verdade.
            'permissions' => $data['role'] === User::ROLE_ADMIN
                ? null
                : Permissions::sanitize($data['permissions'] ?? []),
        ];
    }
}
