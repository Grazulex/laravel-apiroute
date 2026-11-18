<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_version_consumers', function (Blueprint $table) {
            $table->id();
            $table->string('version', 10)->index();
            $table->string('consumer_hash', 64)->index(); // HMAC-SHA256, never the raw identifier
            $table->unsignedInteger('requests_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->date('date')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['version', 'consumer_hash', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_version_consumers');
    }
};
