<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_departments', function (Blueprint $table): void {
            $table->foreignId('manager_contact_id')->nullable()->constrained('external_contacts')->noActionOnDelete();
            $table->string('organizational_title_prefix', 100)->nullable();
            $table->string('position', 255)->nullable();
            $table->string('personal_salutation', 100)->nullable();
            $table->string('professional_title', 100)->nullable();
        });

        // Promote each unit's existing default contact and preserve its addressing data.
        DB::table('external_departments')->orderBy('id')->each(function (object $department): void {
            $contact = DB::table('external_contacts')
                ->where('external_department_id', $department->id)
                ->where('is_default', true)
                ->where('status', 'active')
                ->first();

            if ($contact === null) {
                return;
            }

            DB::table('external_departments')->where('id', $department->id)->update([
                'manager_contact_id' => $contact->id,
                'organizational_title_prefix' => $contact->organizational_title_prefix,
                'position' => $contact->position,
                'personal_salutation' => $contact->personal_salutation,
                'professional_title' => $contact->professional_title,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('external_departments', function (Blueprint $table): void {
            $table->dropForeign(['manager_contact_id']);
            $table->dropColumn([
                'manager_contact_id',
                'organizational_title_prefix',
                'position',
                'personal_salutation',
                'professional_title',
            ]);
        });
    }
};
