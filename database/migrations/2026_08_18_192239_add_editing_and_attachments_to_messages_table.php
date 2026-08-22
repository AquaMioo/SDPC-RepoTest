<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editing, removal and image attachments on a message.
 *
 * `deleted_at` is not SoftDeletes. A removed message keeps its place in the
 * thread and says "message removed" where it was — the sequence of a
 * conversation is evidence of what happened between two parties, and a row
 * that disappears silently rewrites that. The body is cleared on removal so
 * the words are genuinely gone; only the fact of it remains.
 *
 * `body` becomes nullable because an image on its own is a message.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('body');
            $table->timestamp('removed_at')->nullable()->after('edited_at');
            $table->string('attachment_path')->nullable()->after('removed_at');
        });

        // SQLite cannot loosen a column in place, so the nullable change on
        // `body` is done by the schema builder's table rebuild.
        Schema::table('messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            foreach (['edited_at', 'removed_at', 'attachment_path'] as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
