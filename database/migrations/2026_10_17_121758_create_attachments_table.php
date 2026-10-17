<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->scru128('id', 'att_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_id', 'tkt_')->index()->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_comment_id', 'cmt_')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('uploader_type');
            $table->string('uploader_id', 40);
            $table->string('disk');
            $table->text('path');
            $table->text('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->timestampsTz();

            $table->index(['workspace_id', 'ticket_id', 'created_at']);
            $table->index(['uploader_type', 'uploader_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
