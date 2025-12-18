<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_redirects_to_google_oauth()
    {
        // Mock the redirect response
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/oauth/authorize'));

        $response = $this->get(route('auth.google'));

        $response->assertRedirect();
    }

    /** @test */
    public function it_creates_new_user_on_first_google_signin()
    {
        // Create the athlete role for testing
        \App\Models\Role::create(['name' => 'Athlete']);

        // Mock Google user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_123');
        $googleUser->shouldReceive('getName')->andReturn('John Doe');
        $googleUser->shouldReceive('getEmail')->andReturn('john@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Assert user was created
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'google_id' => 'google_123',
            'prefill_suggested_values' => false,
        ]);

        // Assert user has athlete role
        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue($user->hasRole('Athlete'));
        $this->assertFalse($user->prefill_suggested_values);
        $this->assertTrue($user->shouldShowGlobalExercises()); // check that this one remains true

        // Assert user is logged in
        $this->assertAuthenticated();

        // Assert welcome message for new user
        $response->assertRedirect('/mobile-entry/lifts');
        $response->assertSessionHas('success', 'Welcome to our app! Thanks for trying us out. We\'re excited to help you track your fitness journey!');
    }

    /** @test */
    public function it_logs_in_existing_user_with_google_id()
    {
        // Create existing user with Google ID
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Existing User',
            'google_id' => 'google_456',
        ]);

        // Mock Google user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_456');
        $googleUser->shouldReceive('getName')->andReturn('Existing User');
        $googleUser->shouldReceive('getEmail')->andReturn('existing@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Assert user is logged in as existing user
        $this->assertAuthenticatedAs($existingUser);

        // Assert no welcome message for existing user
        $response->assertRedirect('/mobile-entry/lifts');
        $response->assertSessionMissing('success');
    }

    /** @test */
    public function it_links_google_account_to_existing_email()
    {
        // Create existing user without Google ID
        $existingUser = User::factory()->create([
            'email' => 'link@example.com',
            'name' => 'Link User',
            'google_id' => null,
        ]);

        // Mock Google user data with same email
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_789');
        $googleUser->shouldReceive('getName')->andReturn('Link User');
        $googleUser->shouldReceive('getEmail')->andReturn('link@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Assert Google ID was added to existing user
        $existingUser->refresh();
        $this->assertEquals('google_789', $existingUser->google_id);

        // Assert user is logged in
        $this->assertAuthenticatedAs($existingUser);

        // Assert no welcome message (not a new user)
        $response->assertRedirect('/mobile-entry/lifts');
        $response->assertSessionMissing('success');
    }

    /** @test */
    public function it_handles_google_oauth_failure()
    {
        // Mock Socialite to throw exception
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andThrow(new \Exception('OAuth failed'));

        $response = $this->get(route('auth.google.callback'));

        // Assert redirect to login with error
        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Google authentication failed.');

        // Assert user is not logged in
        $this->assertGuest();
    }

    /** @test */
    public function it_prevents_duplicate_users_with_same_email()
    {
        // Create existing user
        $existingUser = User::factory()->create([
            'email' => 'duplicate@example.com',
            'name' => 'Original User',
            'google_id' => null,
        ]);

        // Mock Google user with same email but different name
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_999');
        $googleUser->shouldReceive('getName')->andReturn('Different Name');
        $googleUser->shouldReceive('getEmail')->andReturn('duplicate@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Assert only one user exists with this email
        $this->assertEquals(1, User::where('email', 'duplicate@example.com')->count());

        // Assert the existing user was updated with Google ID
        $existingUser->refresh();
        $this->assertEquals('google_999', $existingUser->google_id);
        $this->assertEquals('Original User', $existingUser->name); // Name should not change

        // Assert user is logged in
        $this->assertAuthenticatedAs($existingUser);
    }

    /** @test */
    public function it_generates_random_password_for_new_google_users()
    {

        // Mock Google user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_password_test');
        $googleUser->shouldReceive('getName')->andReturn('Password Test');
        $googleUser->shouldReceive('getEmail')->andReturn('password@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $this->get(route('auth.google.callback'));

        $user = User::where('email', 'password@example.com')->first();
        
        // Assert password is not null and is hashed
        $this->assertNotNull($user->password);
        $this->assertTrue(password_verify('test', $user->password) === false); // Should not be 'test'
        $this->assertStringStartsWith('$2y$', $user->password); // Should be bcrypt hash
    }

    /** @test */
    public function it_assigns_athlete_role_to_new_users()
    {
        // Create the athlete role for testing
        \App\Models\Role::create(['name' => 'Athlete']);

        // Mock Google user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_role_test');
        $googleUser->shouldReceive('getName')->andReturn('Role Test User');
        $googleUser->shouldReceive('getEmail')->andReturn('roletest@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Assert user was created with athlete role
        $user = User::where('email', 'roletest@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Athlete'));
        $this->assertFalse($user->hasRole('admin'));

        $response->assertRedirect('/mobile-entry/lifts');
    }

    /** @test */
    public function it_creates_default_measurement_types_for_new_users()
    {
        // Create the athlete role for testing
        \App\Models\Role::create(['name' => 'Athlete']);

        // Mock Google user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_measurement_test');
        $googleUser->shouldReceive('getName')->andReturn('Measurement Test User');
        $googleUser->shouldReceive('getEmail')->andReturn('measurementtest@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Assert user was created
        $user = User::where('email', 'measurementtest@example.com')->first();
        $this->assertNotNull($user);

        // Assert default measurement types were created
        $measurementTypes = $user->measurementTypes;
        $this->assertCount(2, $measurementTypes);
        
        $bodyweight = $measurementTypes->where('name', 'Bodyweight')->first();
        $this->assertNotNull($bodyweight);
        $this->assertEquals('lbs', $bodyweight->default_unit);
        
        $waistSize = $measurementTypes->where('name', 'Waist')->first();
        $this->assertNotNull($waistSize);
        $this->assertEquals('in', $waistSize->default_unit);

        $response->assertRedirect('/mobile-entry/lifts');
    }

    /** @test */
    public function it_creates_basic_ingredients_for_new_users()
    {
        // Create the athlete role for testing (required for user creation)
        \App\Models\Role::create(['name' => 'Athlete']);

        // Mock Google user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_ingredients_test');
        $googleUser->shouldReceive('getName')->andReturn('Ingredients Test User');
        $googleUser->shouldReceive('getEmail')->andReturn('ingredientstest@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        // Trigger the Google OAuth callback
        $response = $this->get(route('auth.google.callback'));

        // Assert user was created
        $user = User::where('email', 'ingredientstest@example.com')->first();
        $this->assertNotNull($user);

        // Assert basic ingredients were created from config
        $ingredients = $user->ingredients;
        $this->assertCount(5, $ingredients); // Should match config/user_defaults.php ingredients count

        // Check for specific basic ingredients
        $chickenBreast = $ingredients->where('name', 'Chicken Breast')->first();
        $this->assertNotNull($chickenBreast);
        $this->assertEquals(31.0, $chickenBreast->protein);
        $this->assertEquals(100, $chickenBreast->base_quantity);

        $brownRice = $ingredients->where('name', 'Brown Rice')->first();
        $this->assertNotNull($brownRice);
        $this->assertEquals(2.6, $brownRice->protein);
        $this->assertEquals(23.0, $brownRice->carbs);

        $broccoli = $ingredients->where('name', 'Broccoli')->first();
        $this->assertNotNull($broccoli);
        $this->assertEquals(2.8, $broccoli->protein);

        $oliveOil = $ingredients->where('name', 'Olive Oil')->first();
        $this->assertNotNull($oliveOil);
        $this->assertEquals(13.5, $oliveOil->fats);

        $eggs = $ingredients->where('name', 'Eggs')->first();
        $this->assertNotNull($eggs);
        $this->assertEquals(6.3, $eggs->protein);

        $response->assertRedirect('/mobile-entry/lifts');
        $response->assertSessionHas('success');
    }



    /** @test */
    public function it_creates_sample_meal_for_new_users()
    {
        // Create the athlete role for testing
        \App\Models\Role::create(['name' => 'Athlete']);

        // Mock Google user data
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google_meal_test');
        $googleUser->shouldReceive('getName')->andReturn('Meal Test User');
        $googleUser->shouldReceive('getEmail')->andReturn('mealtest@example.com');

        // Mock Socialite
        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('redirectUrl')
            ->once()
            ->andReturnSelf();
        
        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        // Assert user was created
        $user = User::where('email', 'mealtest@example.com')->first();
        $this->assertNotNull($user);

        // Assert sample meal was created from config
        $meals = $user->meals;
        $this->assertCount(1, $meals);

        $sampleMeal = $meals->first();
        $this->assertEquals('Chicken, Rice & Broccoli', $sampleMeal->name);
        $this->assertEquals('A balanced meal with protein, carbs, and vegetables.', $sampleMeal->comments);

        // Assert meal has the correct ingredients attached
        $mealIngredients = $sampleMeal->ingredients;
        $this->assertCount(4, $mealIngredients); // Should have 4 ingredients from config

        // Check specific ingredient quantities
        $chickenIngredient = $mealIngredients->where('name', 'Chicken Breast')->first();
        $this->assertNotNull($chickenIngredient);
        $this->assertEquals(150, $chickenIngredient->pivot->quantity);

        $riceIngredient = $mealIngredients->where('name', 'Brown Rice')->first();
        $this->assertNotNull($riceIngredient);
        $this->assertEquals(100, $riceIngredient->pivot->quantity);

        $response->assertRedirect('/mobile-entry/lifts');
        $response->assertSessionHas('success');
    }

    /** @test */
    public function it_restores_soft_deleted_user_on_google_signin()
    {
        // Create a user and then soft-delete them
        $user = User::factory()->create([
            'email' => 'softdeleted@example.com',
            'google_id' => null,
        ]);
        $user->delete();

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);

        // Mock Google user data with the same email
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('new_google_id_for_restored_user');
        $googleUser->shouldReceive('getName')->andReturn($user->name);
        $googleUser->shouldReceive('getEmail')->andReturn($user->email);

        // Mock Socialite
        Socialite::shouldReceive('driver')->with('google')->once()->andReturnSelf();
        Socialite::shouldReceive('redirectUrl')->once()->andReturnSelf();
        Socialite::shouldReceive('user')->once()->andReturn($googleUser);

        // Trigger the Google OAuth callback
        $response = $this->get(route('auth.google.callback'));

        // Assert user is restored (not soft-deleted)
        $this->assertNotSoftDeleted('users', [
            'id' => $user->id,
        ]);

        // Assert Google ID was updated
        $user->refresh();
        $this->assertEquals('new_google_id_for_restored_user', $user->google_id);

        // Assert user is logged in
        $this->assertAuthenticatedAs($user);

        // Assert redirect
        $response->assertRedirect('/mobile-entry/lifts');
    }
}
