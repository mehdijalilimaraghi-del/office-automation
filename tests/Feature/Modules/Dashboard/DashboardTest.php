<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_redirects_to_login_instead_of_showing_a_separate_landing_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_authenticated_root_redirects_to_dashboard(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_user_can_open_dashboard_with_separate_personal_and_team_panels(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('stats')
                ->where('canViewTeam', false)
                ->where('stats.my_tasks', 0)
                ->where('stats.my_today_tasks', 0)
                ->where('stats.my_overdue_tasks', 0)
                ->where('stats.team_active_tasks', 0)
                ->has('trend', 7)
                ->where('personalTasks', [])
                ->where('teamTasks', [])
                ->where('projectsOverview', [])
                ->where('sectionsOverview', []));
    }
}
