<?php

namespace Database\Factories;

use App\Enums\MailboxDriver;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mailbox>
 */
class MailboxFactory extends Factory
{
    /**
     * Define the model's default state: an IMAP/SMTP mailbox.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $address = fake()->unique()->safeEmail();

        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'default_desk_id' => fn (array $attributes) => Desk::factory()->state(['workspace_id' => $attributes['workspace_id']]),
            'driver' => MailboxDriver::IMAP,
            'name' => fake()->company().' Support',
            'address' => $address,
            'settings' => [
                'imap' => ['host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => $address, 'password' => 'secret', 'folder' => 'INBOX'],
                'smtp' => ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'username' => $address, 'password' => 'secret'],
            ],
        ];
    }
}
