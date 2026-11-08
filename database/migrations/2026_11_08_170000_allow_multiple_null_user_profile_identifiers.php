<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function ($table) {
            $table->dropUnique('user_profiles_employee_code_unique');
            $table->dropUnique('user_profiles_national_id_unique');
        });

        if (in_array(DB::getDriverName(), ['sqlsrv', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX user_profiles_employee_code_unique ON user_profiles (employee_code) WHERE employee_code IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX user_profiles_national_id_unique ON user_profiles (national_id) WHERE national_id IS NOT NULL');

            return;
        }

        Schema::table('user_profiles', function ($table) {
            $table->unique('employee_code');
            $table->unique('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function ($table) {
            $table->dropUnique('user_profiles_employee_code_unique');
            $table->dropUnique('user_profiles_national_id_unique');
        });

        Schema::table('user_profiles', function ($table) {
            $table->unique('employee_code');
            $table->unique('national_id');
        });
    }
};
