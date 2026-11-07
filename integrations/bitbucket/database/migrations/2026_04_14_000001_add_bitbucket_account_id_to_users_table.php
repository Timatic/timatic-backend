<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // @arch-ignore table-prefix: this ran before integrations kept their own tables
        Schema::table('users', function (Blueprint $table): void {
            $table->string('bitbucket_account_id')->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('bitbucket_account_id');
        });
    }
};
