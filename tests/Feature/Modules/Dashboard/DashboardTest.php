<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_dashboard(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('stats')
                ->where('stats.my_tasks', 0)
                ->where('stats.today_tasks', 0)
                ->where('stats.overdue_tasks', 0)
                ->where('stats.active_projects', 0)
                ->where('stats.completed_tasks', 0)
                ->where('recentTasks', []));
    }
}
