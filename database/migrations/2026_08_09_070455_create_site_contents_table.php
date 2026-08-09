<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();

            // One row per block of copy the admin content screen maintains.
            // The key set is closed — see App\Enums\SiteContentKey.
            $table->string('key')->unique();
            $table->text('body')->nullable();

            // Who last saved it, kept for the audit trail. Nulled rather than
            // cascaded so removing an administrator never deletes the copy.
            $table->foreignIdFor(User::class, 'updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_contents');
    }
};
