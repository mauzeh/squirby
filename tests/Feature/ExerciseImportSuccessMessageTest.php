<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseImportSuccessMessageTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin role and user
        $adminRole = Role::create(['name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);
    }

    /** @test */
    public function exercise_import_success_message_uses_html_lists()
    {
        $tsvData = "Test Exercise 1\tDescription 1\tfalse\n" .
                   "Test Exercise 2\tDescription 2\ttrue";

        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData,
                'import_as_global' => '1'
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        
        // Check that the message contains HTML list elements
        $this->assertStringContainsString('<p>TSV data processed successfully!</p>', $successMessage);
        $this->assertStringContainsString('<p>Imported 2 new global exercises:</p>', $successMessage);
        $this->assertStringContainsString('<ul>', $successMessage);
        $this->assertStringContainsString('<li>Test Exercise 1</li>', $successMessage);
        $this->assertStringContainsString('<li>Test Exercise 2 (bodyweight)</li>', $successMessage);
        $this->assertStringContainsString('</ul>', $successMessage);
    }

    /** @test */
    public function exercise_import_success_message_shows_updates_with_html_lists()
    {
        // Create existing exercise
        $existingExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Existing Exercise',
            'description' => 'Old description',
            'is_bodyweight' => false,
        ]);

        $tsvData = "Existing Exercise\tNew description\ttrue\n" .
                   "New Exercise\tNew exercise description\tfalse";

        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData,
                'import_as_global' => '1'
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        
        // Check that the message contains HTML for both imported and updated
        $this->assertStringContainsString('<p>TSV data processed successfully!</p>', $successMessage);
        $this->assertStringContainsString('<p>Imported 1 new global exercises:</p>', $successMessage);
        $this->assertStringContainsString('<p>Updated 1 existing global exercises:</p>', $successMessage);
        $this->assertStringContainsString('<ul>', $successMessage);
        $this->assertStringContainsString('<li>New Exercise</li>', $successMessage);
        $this->assertStringContainsString('<li>Existing Exercise', $successMessage);
        $this->assertStringContainsString('</ul>', $successMessage);
    }

    /** @test */
    public function exercise_import_success_message_escapes_user_content()
    {
        $tsvData = "Test <script>alert('xss')</script>\tDescription with <b>HTML</b>\tfalse";

        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData,
                'import_as_global' => '1'
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        
        // Check that user content is escaped but HTML structure is preserved
        $this->assertStringContainsString('<ul>', $successMessage);
        $this->assertStringContainsString('<li>', $successMessage);
        $this->assertStringContainsString('</ul>', $successMessage);
        
        // User content should be escaped
        $this->assertStringContainsString('&lt;script&gt;', $successMessage);
        $this->assertStringNotContainsString('<script>', $successMessage);
    }
}