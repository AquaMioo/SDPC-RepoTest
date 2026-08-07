<?php

use App\Enums\VerificationStatus;
use App\Models\Team;
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
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            /**
             * A client is a team, so the business profile extends the team
             * rather than a single user. This keeps multi-staff businesses
             * working with the membership model already in the application.
             */
            $table->foreignIdFor(Team::class)->unique()->constrained()->cascadeOnDelete();

            $table->string('business_name');
            $table->text('business_description')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('logo_path')->nullable();

            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('website_url')->nullable();
            $table->string('facebook_url')->nullable();

            $table->string('permit_path')->nullable();
            $table->string('verification_status')
                ->default(VerificationStatus::Unverified->value)
                ->index();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};
