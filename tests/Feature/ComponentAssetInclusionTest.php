<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentAssetInclusionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_statically_includes_all_component_styles()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('mobile-entry.lifts'));

        $response->assertStatus(200);
        $response->assertSee('components/list.css', false);
        $response->assertSee('components/table.css', false);
        $response->assertSee('components/form.css', false);
    }

    /** @test */
    public function it_dynamically_includes_required_scripts()
    {
        $user = User::factory()->create();

        $chartComponent = \App\Services\ComponentBuilder::chart('my-chart', 'My Chart')->build();

        $data = [
            'components' => [$chartComponent],
            'autoscroll' => false
        ];

        $response = $this->actingAs($user)->view('mobile-entry.flexible', compact('data'));

        $response->assertSee('chart-component.js', false);
    }
}
