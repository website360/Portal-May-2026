<?php

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default(User::ROLE_MEMBER)->after('password');
            $table->json('permissions')->nullable()->after('role');
        });

        /*
         * Quem já existia vira administrador. O contrário trancaria todo mundo
         * para fora de um sistema que até agora não tinha permissão nenhuma —
         * inclusive de dentro da tela que gerencia permissões.
         */
        DB::table('users')->update([
            'role' => User::ROLE_ADMIN,
            'permissions' => json_encode(Permissions::all(Permissions::WRITE)),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'permissions']);
        });
    }
};
