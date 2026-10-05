<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Correspondence;

use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Models\CorrespondenceReferral;
use App\Modules\Correspondence\Services\CorrespondenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrespondenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_draft_with_generated_registration_number(): void
    {
        $user = User::factory()->create();

        $correspondence = app(CorrespondenceService::class)->create([
            'type' => 'incoming',
            'subject' => 'نامه آزمایشی',
        ], $user);

        $this->assertSame('draft', $correspondence->status);
        $this->assertNotEmpty($correspondence->registration_number);
        $this->assertDatabaseHas('correspondences', [
            'id' => $correspondence->id,
            'type' => 'incoming',
            'subject' => 'نامه آزمایشی',
        ]);
    }

    public function test_only_registered_correspondence_can_be_referred(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();
        $service = app(CorrespondenceService::class);

        $correspondence = $service->create([
            'type' => 'internal',
            'subject' => 'ارجاع',
        ], $user);

        $this->expectException(\DomainException::class);
        $service->refer($correspondence, $recipient, $user);
    }

    public function test_registered_correspondence_can_be_referred(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();
        $service = app(CorrespondenceService::class);

        $correspondence = $service->create([
            'type' => 'internal',
            'subject' => 'ارجاع',
        ], $user);

        $service->register($correspondence);
        $referral = $service->refer($correspondence, $recipient, $user, 'بررسی و اقدام');

        $this->assertInstanceOf(CorrespondenceReferral::class, $referral);
        $this->assertDatabaseHas('correspondence_referrals', [
            'correspondence_id' => $correspondence->id,
            'to_user_id' => $recipient->id,
            'instruction' => 'بررسی و اقدام',
        ]);
    }
}