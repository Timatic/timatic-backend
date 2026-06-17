<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command as BaseCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class GenerateApiToken extends Command
{
    protected $signature = 'apitoken:generate';

    protected $description = 'Generate a new API token with optional expiration and permissions';

    public function handle(): int
    {
        try {
            // Gather inputs using Laravel Prompts
            $title = text(
                label: 'What is the title for this API token?',
                placeholder: 'Production API',
                required: true,
                validate: fn ($value) => match (true) {
                    strlen($value) < 3 => 'Title must be at least 3 characters',
                    strlen($value) > 255 => 'Title must not exceed 255 characters',
                    default => null
                }
            );

            $description = textarea(
                label: 'Description (optional)',
                placeholder: 'Used for server integration'
            );

            $shouldExpire = confirm(
                label: 'Should this token expire?',
                default: false
            );

            $expiresAt = null;
            if ($shouldExpire) {
                $expiresAt = $this->getExpiresAt();
            }

            $permissions = $this->getPermissions();
            $roles = $this->getRoles();

            // Generate secure token
            $plainToken = Str::random(64);

            // Create token in database with transaction
            $token = $this->createToken($title, $description, $expiresAt, $plainToken, $permissions, $roles);

            // Display token information
            $this->displayToken($token, $plainToken, $permissions, $roles);

            return BaseCommand::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to create API token: '.$e->getMessage());

            return BaseCommand::FAILURE;
        }
    }

    private function getExpiresAt(): Carbon
    {
        $expiresAt = text(
            label: 'Expiration date',
            placeholder: 'YYYY-MM-DD or YYYY-MM-DD HH:MM:SS',
            hint: 'Example: 2026-12-31',
            required: true,
            validate: function ($value) {
                if (empty($value)) {
                    return 'Expiration date is required';
                }

                try {
                    $date = Carbon::parse($value);

                    if ($date->isPast()) {
                        return 'Expiration date must be in the future';
                    }

                    return null;
                } catch (\Exception $e) {
                    return 'Invalid date format. Expected: YYYY-MM-DD or YYYY-MM-DD HH:MM:SS';
                }
            }
        );

        return Carbon::parse($expiresAt);
    }

    /**
     * @return array<int, string>
     */
    private function getPermissions(): array
    {
        $availablePermissions = Permission::where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        if (empty($availablePermissions)) {
            $this->warn('No permissions available in the system');

            return [];
        }

        // Add "All permissions" option at the beginning
        $options = ['* All permissions' => '* All permissions'] + array_combine($availablePermissions, $availablePermissions);

        $selected = multiselect(
            label: 'Select permissions to assign',
            options: $options,
            hint: 'Use space to select, enter to confirm'
        );

        // If "All permissions" is selected, return all permissions
        if (in_array('* All permissions', $selected)) {
            /** @var array<int, string> */
            return array_values($availablePermissions);
        }

        /** @var array<int, string> */
        return array_values($selected);
    }

    /**
     * @return array<int, string>
     */
    private function getRoles(): array
    {
        $availableRoles = Role::where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        if (empty($availableRoles)) {
            $this->warn('No roles available in the system');

            return [];
        }

        // Add "All roles" option at the beginning
        $options = ['* All roles' => '* All roles'] + array_combine($availableRoles, $availableRoles);

        $selected = multiselect(
            label: 'Select roles to assign',
            options: $options,
            hint: 'Use space to select, enter to confirm'
        );

        // If "All roles" is selected, return all roles
        if (in_array('* All roles', $selected)) {
            /** @var array<int, string> */
            return array_values($availableRoles);
        }

        /** @var array<int, string> */
        return array_values($selected);
    }

    /**
     * @param  array<int, string>  $permissions
     * @param  array<int, string>  $roles
     */
    private function createToken(
        string $title,
        ?string $description,
        ?Carbon $expiresAt,
        string $plainToken,
        array $permissions,
        array $roles
    ): ApiToken {
        return DB::transaction(function () use ($title, $description, $expiresAt, $plainToken, $permissions, $roles) {
            // Create the token
            $token = ApiToken::create([
                'external_id' => Str::uuid()->toString(),
                'title' => $title,
                'description' => $description,
                'key' => hash('sha512', $plainToken),
                'expires_at' => $expiresAt,
            ]);

            // Assign permissions
            if (! empty($permissions)) {
                $permissionModels = Permission::whereIn('name', $permissions)
                    ->where('guard_name', 'web')
                    ->get();
                $token->syncPermissions($permissionModels);
            }

            // Assign roles
            if (! empty($roles)) {
                $roleModels = Role::whereIn('name', $roles)
                    ->where('guard_name', 'web')
                    ->get();
                $token->syncRoles($roleModels);
            }

            return $token;
        });
    }

    /**
     * @param  array<int, string>  $permissions
     * @param  array<int, string>  $roles
     */
    private function displayToken(ApiToken $token, string $plainToken, array $permissions, array $roles): void
    {
        $this->newLine();
        $this->components->success('API Token created successfully!');
        $this->newLine();

        // Warning box
        $this->components->alert('IMPORTANT: Save this token now. It will not be shown again!');
        $this->newLine();

        // Token details
        $this->components->twoColumnDetail('Token', $plainToken);
        $this->components->twoColumnDetail('ID', $token->external_id);
        $this->components->twoColumnDetail('Title', $token->title);
        $this->components->twoColumnDetail(
            'Expires',
            $token->expires_at ? $token->expires_at->toDateTimeString() : 'Never'
        );
        $this->components->twoColumnDetail(
            'Permissions',
            ! empty($permissions) ? implode(', ', $permissions) : 'None'
        );
        $this->components->twoColumnDetail(
            'Roles',
            ! empty($roles) ? implode(', ', $roles) : 'None'
        );

        $this->newLine();
        $this->info('Usage Example:');
        $this->line('  curl -H "Authorization: Bearer '.$plainToken.'" https://your-api.com/api/endpoint');
        $this->newLine();
    }
}
