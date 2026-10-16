<?php

use Illuminate\Database\Migrations\Migration;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * ponytail: seeded in a migration so every install (cloud, self-hosted, tests) has the
 * personal access client without a manual `passport:client --personal` step.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient(config('app.name').' Personal Access Client');
    }

    public function down(): void
    {
        Passport::client()->newQuery()->whereLike('grant_types', '%"personal_access"%')->delete();
    }
};
