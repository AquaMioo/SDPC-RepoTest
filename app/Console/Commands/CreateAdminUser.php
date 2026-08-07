<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminUser extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create
                            {--name= : The administrator name}
                            {--email= : The administrator email address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an administrator account for the admin portal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $input = [
            'name' => $this->stringOption('name') ?? text('Name', required: true),
            'email' => $this->stringOption('email') ?? text('Email address', required: true),
            'password' => password('Password', required: true),
            'password_confirmation' => password('Confirm password', required: true),
        ];

        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ])->save();

        $this->components->info("Administrator [{$user->email}] created. Sign in at ".route('admin.login').'.');

        return self::SUCCESS;
    }

    /**
     * Get a command option as a string, if it was provided.
     */
    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
