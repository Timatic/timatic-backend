<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('customers')->where('external_id', 10783)->update(['hourly_rate' => '155']);
            DB::table('customers')->where('external_id', 30014)->update(['hourly_rate' => '100']);
            DB::table('customers')->where('external_id', 50340)->update(['hourly_rate' => '155']);
            DB::table('customers')->where('external_id', 50487)->update(['hourly_rate' => '93']);
            DB::table('customers')->where('external_id', 50558)->update(['hourly_rate' => '106']);
            DB::table('customers')->where('external_id', 51761)->update(['hourly_rate' => '101']);
            DB::table('customers')->where('external_id', 900748)->update(['hourly_rate' => '108']);
            DB::table('customers')->where('external_id', 901037)->update(['hourly_rate' => '155']);
            DB::table('customers')->where('external_id', 930057)->update(['hourly_rate' => '109']);
            DB::table('customers')->where('external_id', 930150)->update(['hourly_rate' => '95']);
            DB::table('customers')->where('external_id', 930206)->update(['hourly_rate' => '100']);
            DB::table('customers')->where('external_id', 930211)->update(['hourly_rate' => '120']);
            DB::table('customers')->where('external_id', 930230)->update(['hourly_rate' => '155']);
            DB::table('customers')->where('external_id', 930295)->update(['hourly_rate' => '89.10']);
            DB::table('customers')->where('external_id', 930530)->update(['hourly_rate' => '95']);
            DB::table('customers')->where('external_id', 930607)->update(['hourly_rate' => '155']);
            DB::table('customers')->where('external_id', 930685)->update(['hourly_rate' => '91']);
            DB::table('customers')->where('external_id', 930801)->update(['hourly_rate' => '103']);
            DB::table('customers')->where('external_id', 930944)->update(['hourly_rate' => '125']);
            DB::table('customers')->where('external_id', 930853)->update(['hourly_rate' => '134']);
            DB::table('customers')->where('external_id', 930369)->update(['hourly_rate' => '145']);
            DB::table('customers')->where('external_id', 930535)->update(['hourly_rate' => '145']);
            DB::table('customers')->where('external_id', 930351)->update(['hourly_rate' => '140']);
            DB::table('customers')->where('external_id', 930703)->update(['hourly_rate' => '138']);
            DB::table('customers')->where('external_id', 930654)->update(['hourly_rate' => '138']);
            DB::table('customers')->where('external_id', 930704)->update(['hourly_rate' => '138']);
            DB::table('customers')->where('external_id', 931044)->update(['hourly_rate' => '138']);
            DB::table('customers')->where('external_id', 930860)->update(['hourly_rate' => '145']);
            DB::table('customers')->where('external_id', 930635)->update(['hourly_rate' => '145']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('customers')->update(['hourly_rate' => null]);
    }
};
