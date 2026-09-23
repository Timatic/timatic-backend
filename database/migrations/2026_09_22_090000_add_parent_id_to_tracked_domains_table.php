<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A user who accepts a shared mapping gets a copy of their own. The copy remembers where it came
     * from, so a change to the shared mapping can still be traced to the mappings that followed it.
     */
    public function up(): void
    {
        Schema::table('tracked_domains', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('user_id')->constrained('tracked_domains')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracked_domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
