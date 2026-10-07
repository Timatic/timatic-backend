<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_domains', function (Blueprint $table) {
            $table->string('path')->nullable()->default(null)->change();
        });

        DB::table('tracked_domains')->where('path', '')->update(['path' => null]);
    }

    public function down(): void
    {
        DB::table('tracked_domains')->whereNull('path')->update(['path' => '']);

        Schema::table('tracked_domains', function (Blueprint $table) {
            $table->string('path')->default('')->change();
        });
    }
};
