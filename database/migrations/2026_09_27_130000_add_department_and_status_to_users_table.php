<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('id');

            $table->string('status', 20)
                ->default('active')
                ->after('password');

            $table->index('status');
            $table->index('department_id');

            $table->foreign('department_id')
                ->references('id')
                ->on('departments');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'department_id',
                'status',
            ]);
        });
    }
};