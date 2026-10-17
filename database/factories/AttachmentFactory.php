<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->word().'.pdf';

        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'ticket_id' => Ticket::factory(),
            'uploader_type' => 'user',
            'uploader_id' => User::factory(),
            'disk' => config('harbor.attachments.disk'),
            'path' => 'attachments/'.$filename,
            'filename' => $filename,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 1_000_000),
            'checksum' => hash('sha256', $filename),
        ];
    }
}
