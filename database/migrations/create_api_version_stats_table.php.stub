<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_version_stats', function (Blueprint $table) {
            $table->id();
            $table->string('version', 10)->index();
            $table->string('endpoint')->index();
            $table->string('method', 10);
            $table->unsignedInteger('requests_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->date('date')->index();
            $table->unsignedTinyInteger('hour')->nullable(); // For hourly aggregation
            $table->timestamps();

            $table->unique(['version', 'endpoint', 'method', 'date', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_version_stats');
    }
};
