<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('event_type_id')->nullable()->after('is_internal');
            $table->foreign('event_type_id')->references('id')->on('event_types')->cascadeOnUpdate()->nullOnDelete();
        });
    }
};
