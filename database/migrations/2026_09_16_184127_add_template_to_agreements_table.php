<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which wording each agreement is written in.
 *
 * The contract screen now shows the school's Memorandum of Agreement. An
 * agreement nobody has signed yet simply moves to it. One that somebody has
 * signed keeps the clauses it was signed against, because a signature has to
 * stay attached to the words it was given for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->string('template', 20)->default('memorandum')->after('version');
        });

        DB::table('agreements')
            ->whereExists(fn ($signatures) => $signatures
                ->selectRaw('1')
                ->from('agreement_signatures')
                ->whereColumn('agreement_signatures.agreement_id', 'agreements.id'))
            ->update(['template' => 'clauses']);
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('template');
        });
    }
};
