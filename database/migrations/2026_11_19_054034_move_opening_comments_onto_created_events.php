<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A ticket's opening message used to record its own `comment_added` event, flagged `opening`.
     * It now belongs to the `created` event as its `comment_id`.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE ticket_events AS created
            SET properties = jsonb_build_object('comment_id', opening.properties->'comment_id')
            FROM ticket_events AS opening
            WHERE created.ticket_id = opening.ticket_id
              AND created.type = 'created'
              AND opening.type = 'comment_added'
              AND (opening.properties->>'opening')::boolean
            SQL);

        DB::table('ticket_events')
            ->where('type', 'comment_added')
            ->where('properties->opening', true)
            ->delete();
    }
};
