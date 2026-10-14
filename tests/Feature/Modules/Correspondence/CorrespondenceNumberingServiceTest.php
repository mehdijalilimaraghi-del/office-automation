<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Correspondence;

use App\Core\Settings\Services\SystemSettingsService;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceNumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrespondenceNumberingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbering_supports_the_three_presets_and_increments_a_shared_sequence(): void
    {
        $correspondence = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه آزمایشی',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => User::factory()->create(['status' => 'active'])->id,
        ]);
        $settings = app(SystemSettingsService::class);
        $service = app(CorrespondenceNumberingService::class);

        $settings->set(['correspondence.numbering.format' => 'simple']);
        $this->assertSame('000001', $service->nextNumber($correspondence));

        $settings->set(['correspondence.numbering.format' => 'year']);
        $this->assertStringEndsWith('/000002', $service->nextNumber($correspondence));

        $settings->set(['correspondence.numbering.format' => 'year_department']);
        $this->assertStringContainsString('/ORG/000003', $service->nextNumber($correspondence));
    }

    public function test_custom_numbering_order_is_applied(): void
    {
        $correspondence = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه داخلی',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => User::factory()->create(['status' => 'active'])->id,
        ]);
        app(SystemSettingsService::class)->set([
            'correspondence.numbering.format' => 'custom',
            'correspondence.numbering.custom_order' => 'sequence,year,department',
            'correspondence.numbering.separator' => '-',
        ]);

        $number = app(CorrespondenceNumberingService::class)->nextNumber($correspondence);

        $this->assertMatchesRegularExpression('/^000001-\d{4}-ORG$/', $number);
    }
}
