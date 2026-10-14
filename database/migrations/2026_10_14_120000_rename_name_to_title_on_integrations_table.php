<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->renameColumn('name', 'title');
        });
    }

    public function down(): void
    {
        DB::table('integrations')->whereNull('title')->update(['title' => DB::raw('type')]);

        Schema::table('integrations', function (Blueprint $table) {
            $table->renameColumn('title', 'name');
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};
