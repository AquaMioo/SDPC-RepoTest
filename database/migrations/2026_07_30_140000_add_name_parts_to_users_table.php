<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The sign up form collects the first and last name separately. The
     * existing "name" column stays as the canonical display name so nothing
     * that already reads it has to change.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
        });

        DB::table('users')->select('id', 'name')->orderBy('id')->chunk(200, function ($users) {
            foreach ($users as $user) {
                $name = trim((string) $user->name);

                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => Str::before($name, ' ') ?: $name,
                    'last_name' => Str::contains($name, ' ') ? Str::afterLast($name, ' ') : null,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
