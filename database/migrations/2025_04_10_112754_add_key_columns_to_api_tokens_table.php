<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dateTime('expires_at')->after('external_id')->nullable();
            $table->string('key')->after('external_id')->nullable();
            $table->text('description')->after('external_id')->nullable();
            $table->string('title')->after('external_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn('expires_at');
            $table->dropColumn('key');
            $table->dropColumn('description');
            $table->dropColumn('title');
        });
    }
};
