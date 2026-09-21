<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_domains', function (Blueprint $table) {
            $table->string('path')->default('')->after('domain');
            $table->dropUnique(['domain']);
            $table->unique(['domain', 'path']);
        });
    }

    public function down(): void
    {
        Schema::table('tracked_domains', function (Blueprint $table) {
            $table->dropUnique(['domain', 'path']);
            $table->unique('domain');
            $table->dropColumn('path');
        });
    }
};
