<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->boolean('is_primary')->default(false);
            $table->dateTime('joined_at')->nullable();

            $table->timestamps();

            $table->unique([
                'department_id',
                'user_id',
            ]);

            $table->index('user_id');
            $table->index('is_primary');
        });

        DB::table('users')
            ->whereNotNull('department_id')
            ->orderBy('id')
            ->eachById(function ($user): void {
                DB::table('department_memberships')->insert([
                    'department_id' => $user->department_id,
                    'user_id' => $user->id,
                    'is_primary' => true,
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_memberships');
    }
};
