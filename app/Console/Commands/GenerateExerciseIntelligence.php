<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;
use Illuminate\Support\Facades\Http;

/**
 * Generate exercise intelligence data using Google Gemini AI for exercises that lack it.
 * 
 * This command uses Google's Gemini AI to automatically generate biomechanically accurate
 * exercise intelligence data including muscle activation patterns, movement archetypes,
 * difficulty levels, and recovery requirements.
 * 
 * PREREQUISITES:
 * 
 * 1. Gemini API Key (FREE):
 *    - Get your key at: https://aistudio.google.com/app/apikey
 *    - Add to .env: GEMINI_API_KEY=your-key-here
 *    - Or pass via --api-key option
 * 
 * 2. Exercises must exist in database (but without intelligence data)
 * 
 * BASIC USAGE:
 * 
 * Generate intelligence for global exercises:
 *   php artisan exercises:generate-intelligence --global
 * 
 * Generate for specific exercise:
 *   php artisan exercises:generate-intelligence --exercise-id=123
 * 
 * Generate for user exercises:
 *   php artisan exercises:generate-intelligence --user --user-id=5
 * 
 * COMMAND OPTIONS:
 * 
 * --exercise-id=ID       Generate for specific exercise ID
 * --global               Only process global exercises (most common)
 * --user                 Only process user exercises
 * --user-id=ID           Process specific user's exercises
 * --limit=N              Maximum number to process (default: 10)
 * --output=PATH          Output file path (default: storage/app/generated_intelligence.json)
 * --api-key=KEY          Gemini API key (or set GEMINI_API_KEY in .env)
 * --model=MODEL          Gemini model to use (default: gemini-1.5-flash)
 * --append               Append to existing file instead of overwriting
 * 
 * MODEL SELECTION:
 * 
 * By default, the command auto-detects the best available Gemini model.
 * You can override with --model if needed:
 *   --model=gemini-2.5-flash  - Mid-size model with 1M token context
 *   --model=gemini-2.5-pro    - Most accurate, best for complex exercises
 *   --model=gemini-2.0-flash  - Fast and versatile
 * 
 * COMPLETE WORKFLOW:
 * 
 * 1. Find exercises without intelligence:
 *    php artisan exercises:list-without-intelligence --global
 * 
 * 2. Generate intelligence data:
 *    php artisan exercises:generate-intelligence --global --limit=50
 * 
 *    This will:
 *    - Query Gemini AI for each exercise
 *    - Generate biomechanically accurate intelligence data
 *    - Save to storage/app/generated_intelligence.json
 *    - Show progress bar and summary
 * 
 * 3. Review the generated JSON:
 *    cat storage/app/generated_intelligence.json
 * 
 *    Example output:
 *    {
 *      "bench_press": {
 *        "canonical_name": "bench_press",
 *        "muscle_data": {
 *          "muscles": [
 *            {
 *              "name": "pectoralis_major",
 *              "role": "primary_mover",
 *              "contraction_type": "isotonic"
 *            },
 *            {
 *              "name": "anterior_deltoid",
 *              "role": "synergist",
 *              "contraction_type": "isotonic"
 *            }
 *          ]
 *        },
 *        "primary_mover": "pectoralis_major",
 *        "largest_muscle": "pectoralis_major",
 *        "movement_archetype": "push",
 *        "category": "strength",
 *        "difficulty_level": 3,
 *        "recovery_hours": 48
 *      }
 *    }
 * 
 * 4. Sync to database:
 *    php artisan exercises:sync-intelligence --file=storage/app/generated_intelligence.json
 * 
 *    Or preview first:
 *    php artisan exercises:sync-intelligence --file=storage/app/generated_intelligence.json --dry-run
 * 
 * WHAT THE AI GENERATES:
 * 
 * - Canonical Name: Snake_case identifier for the exercise
 * - Muscle Data: All involved muscles with their roles
 *   - Primary movers: Main working muscles (isotonic contraction)
 *   - Synergists: Assisting muscles (isotonic contraction)
 *   - Stabilizers: Supporting muscles (isometric contraction)
 * - Movement Archetype: push, pull, squat, hinge, carry, or core
 * - Category: strength, cardio, mobility, plyometric, or flexibility
 * - Difficulty Level: 1-5 scale (1=beginner, 5=expert)
 * - Recovery Hours: Recommended time between sessions (24-96 hours)
 * 
 * ADVANCED USAGE:
 * 
 * Generate in batches to manage processing:
 *   php artisan exercises:generate-intelligence --global --limit=10 --output=storage/app/batch1.json
 *   php artisan exercises:generate-intelligence --global --limit=10 --output=storage/app/batch1.json --append
 * 
 * Override auto-detected model:
 *   php artisan exercises:generate-intelligence --global --model=gemini-2.5-pro
 * 
 * Custom output location:
 *   php artisan exercises:generate-intelligence --global --output=database/imports/my_intelligence.json
 * 
 * TIPS FOR BEST RESULTS:
 * 
 * 1. Provide good exercise descriptions - AI uses title and description
 * 2. Review before syncing - AI can make mistakes, always review generated data
 * 3. Start small - Test with --limit=5 first to verify quality
 * 4. Use gemini-1.5-pro for complex exercises - More accurate than flash
 * 5. Batch processing - Process in small batches for better control
 * 
 * COST CONSIDERATIONS:
 * 
 * Gemini API is FREE for most use cases:
 * - gemini-1.5-flash: 15 requests per minute (free tier)
 * - gemini-1.5-pro: 2 requests per minute (free tier)
 * - No cost for typical usage volumes
 * 
 * The command includes a 0.5s delay between requests to respect rate limits.
 * 
 * TROUBLESHOOTING:
 * 
 * "Gemini API key is required":
 *   - Add GEMINI_API_KEY=your-key to .env file
 *   - Get key at: https://aistudio.google.com/app/apikey
 * 
 * Rate limit errors:
 *   - Reduce --limit to process fewer at once
 *   - Wait a minute and try again
 *   - Use gemini-1.5-flash (higher rate limit than pro)
 * 
 * Invalid JSON responses:
 *   - Try using gemini-1.5-pro instead of flash
 *   - Command automatically strips markdown formatting
 *   - Failed exercises are logged and skipped
 * 
 * AI generates incorrect muscle data:
 *   - Review and manually correct the JSON file
 *   - Provide better exercise descriptions in database
 *   - Use sync command's --dry-run to preview before applying
 * 
 * EXAMPLE COMPLETE WORKFLOW:
 * 
 * # 1. Check what needs intelligence
 * php artisan exercises:list-without-intelligence --global
 * 
 * # 2. Generate intelligence for 20 exercises
 * php artisan exercises:generate-intelligence --global --limit=20
 * 
 * # 3. Review the output
 * cat storage/app/generated_intelligence.json
 * 
 * # 4. Preview sync (dry run)
 * php artisan exercises:sync-intelligence --file=storage/app/generated_intelligence.json --dry-run
 * 
 * # 5. Apply to database
 * php artisan exercises:sync-intelligence --file=storage/app/generated_intelligence.json
 * 
 * # 6. Verify
 * php artisan exercises:list-without-intelligence --global
 * 
 * INTEGRATION WITH RECOMMENDATION ENGINE:
 * 
 * The generated intelligence data is used by the recommendation engine for:
 * - Muscle group balancing in workout suggestions
 * - Recovery time calculations between sessions
 * - Exercise difficulty progression planning
 * - Movement pattern variety in program design
 * - Biomechanical analysis for form guidance
 * 
 * After syncing intelligence data, the recommendation engine will automatically
 * use it to provide better exercise suggestions to users.
 */
class GenerateExerciseIntelligence extends Command
{
    protected $signature = 'exercises:generate-intelligence
                            {--exercise-id= : Generate for specific exercise ID}
                            {--global : Only generate for global exercises}
                            {--user : Only generate for user exercises}
                            {--user-id= : Generate for specific user\'s exercises}
                            {--limit=10 : Maximum number of exercises to process}
                            {--output= : Output file path (default: storage/app/generated_intelligence.json)}
                            {--api-key= : Gemini API key (or set GEMINI_API_KEY env var)}
                            {--model= : Gemini model to use (auto-detects best available if not specified)}
                            {--append : Append to existing output file instead of overwriting}';

    protected $description = 'Generate exercise intelligence data using Google Gemini AI for exercises that lack it';

    private array $generatedIntelligence = [];
    private int $successCount = 0;
    private int $failureCount = 0;

    public function handle()
    {
        $apiKey = $this->option('api-key') ?: env('GEMINI_API_KEY');
        
        if (!$apiKey) {
            $this->error('Gemini API key is required. Set GEMINI_API_KEY in .env or use --api-key option.');
            $this->comment('Get your free API key at: https://aistudio.google.com/app/apikey');
            return Command::FAILURE;
        }

        // Auto-detect best model if not specified
        $model = $this->option('model');
        if (!$model) {
            $this->info('Auto-detecting best available Gemini model...');
            $model = $this->detectBestModel($apiKey);
            if (!$model) {
                $this->error('Failed to detect available models. Please specify --model manually.');
                return Command::FAILURE;
            }
            $this->info("Using model: {$model}");
        }

        $this->info('Starting Gemini AI-powered exercise intelligence generation...');
        $this->newLine();

        // Get exercises without intelligence
        $exercises = $this->getExercisesWithoutIntelligence();

        if ($exercises->isEmpty()) {
            $this->info('✓ No exercises found that need intelligence data!');
            return Command::SUCCESS;
        }

        $this->info("Found {$exercises->count()} exercises to process");
        $this->newLine();
        
        // Display list of exercises to be processed
        $this->info("Exercises to process:");
        $exerciseList = $exercises->map(function ($exercise) {
            $type = $exercise->user_id ? 'User' : 'Global';
            $hasIntelligence = $exercise->intelligence ? ' (has intelligence)' : '';
            return [
                'ID' => $exercise->id,
                'Title' => $exercise->title,
                'Type' => $type,
                'Status' => $hasIntelligence ?: 'No intelligence',
            ];
        })->toArray();
        
        $this->table(['ID', 'Title', 'Type', 'Status'], $exerciseList);
        $this->newLine();

        // Load existing data if appending
        $outputPath = $this->getOutputPath();
        if ($this->option('append') && file_exists($outputPath)) {
            $existing = json_decode(file_get_contents($outputPath), true);
            if ($existing) {
                $this->generatedIntelligence = $existing;
                $this->info("Loaded " . count($existing) . " existing intelligence records");
            }
        }

        // Process each exercise
        $exerciseNumber = 0;
        $totalExercises = $exercises->count();

        foreach ($exercises as $exercise) {
            $exerciseNumber++;
            $this->newLine();
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Processing exercise {$exerciseNumber}/{$totalExercises}");
            $this->comment("Exercise: {$exercise->title}");
            if ($exercise->description) {
                $this->line("Description: " . substr($exercise->description, 0, 100) . (strlen($exercise->description) > 100 ? '...' : ''));
            }
            
            $this->generateIntelligenceForExercise($exercise, $apiKey);
            
            // Small delay to avoid rate limiting
            $this->line("Waiting 0.5s before next request...");
            usleep(500000); // 0.5 seconds
        }

        $this->newLine(2);

        // Save results
        $this->saveResults($outputPath);

        // Display summary
        $this->displaySummary($outputPath);

        return Command::SUCCESS;
    }

    private function getExercisesWithoutIntelligence()
    {
        // If specific exercise ID is provided, get it directly (even if it has intelligence)
        if ($exerciseId = $this->option('exercise-id')) {
            $exercise = Exercise::find($exerciseId);
            if (!$exercise) {
                $this->error("Exercise with ID {$exerciseId} not found.");
                return collect([]);
            }
            
            if ($exercise->intelligence) {
                $this->warn("Note: Exercise already has intelligence data. It will be regenerated.");
            }
            
            return collect([$exercise]);
        }

        // Otherwise, query for exercises without intelligence
        $query = Exercise::doesntHave('intelligence');

        if ($this->option('global')) {
            $query->whereNull('user_id');
        } elseif ($this->option('user')) {
            $query->whereNotNull('user_id');
        }

        if ($userId = $this->option('user-id')) {
            $query->where('user_id', $userId);
        }

        $limit = (int) $this->option('limit');
        
        return $query->limit($limit)->get();
    }

    private function detectBestModel(string $apiKey): ?string
    {
        try {
            $response = Http::timeout(10)->get(
                "https://generativelanguage.googleapis.com/v1/models?key={$apiKey}"
            );

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $models = $data['models'] ?? [];

            // Priority order: prefer flash models for speed, then pro for accuracy
            $preferredModels = [
                'gemini-2.5-flash',
                'gemini-2.0-flash',
                'gemini-2.5-pro',
                'gemini-2.0-flash-001',
                'gemini-1.5-flash',
                'gemini-1.5-pro',
            ];

            foreach ($preferredModels as $preferred) {
                foreach ($models as $model) {
                    $modelName = str_replace('models/', '', $model['name']);
                    if ($modelName === $preferred && in_array('generateContent', $model['supportedGenerationMethods'] ?? [])) {
                        return $modelName;
                    }
                }
            }

            // Fallback: use first model that supports generateContent
            foreach ($models as $model) {
                if (in_array('generateContent', $model['supportedGenerationMethods'] ?? [])) {
                    return str_replace('models/', '', $model['name']);
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateIntelligenceForExercise(Exercise $exercise, string $apiKey): void
    {
        try {
            $this->line("→ Building AI prompt...");
            $prompt = $this->buildPrompt($exercise);
            
            $this->line("→ Detecting best model...");
            $model = $this->option('model') ?: $this->detectBestModel($apiKey);
            $this->comment("  Using model: {$model}");
            
            $this->line("→ Calling Gemini API...");
            $response = $this->callGemini($prompt, $apiKey, $model);
            
            if ($response) {
                $this->line("→ Parsing AI response...");
                $intelligence = $this->parseAIResponse($response, $exercise);
                
                if ($intelligence) {
                    $key = $intelligence['canonical_name'] ?? $exercise->canonical_name ?? $exercise->title;
                    $this->generatedIntelligence[$key] = $intelligence;
                    $this->successCount++;
                    
                    $this->info("✓ Successfully generated intelligence");
                    $this->comment("  Canonical name: {$key}");
                    $this->comment("  Movement: {$intelligence['movement_archetype']}");
                    $this->comment("  Primary mover: {$intelligence['primary_mover']}");
                    $this->comment("  Difficulty: {$intelligence['difficulty_level']}/5");
                    $this->comment("  Recovery: {$intelligence['recovery_hours']} hours");
                    
                    $this->newLine();
                    $this->line("  Generated JSON:");
                    $this->line(json_encode($intelligence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    $this->failureCount++;
                    $this->error("✗ Failed to parse AI response");
                }
            } else {
                $this->failureCount++;
                $this->error("✗ No response from API");
            }
        } catch (\Exception $e) {
            $this->failureCount++;
            $this->error("✗ Exception: " . $e->getMessage());
        }
    }

    private function buildPrompt(Exercise $exercise): string
    {
        return <<<PROMPT
You are an expert exercise physiologist and biomechanics specialist. Generate detailed exercise intelligence data for the following exercise.

Exercise Title: {$exercise->title}
Exercise Description: {$exercise->description}

Provide a JSON response with the following structure (respond ONLY with valid JSON, no markdown or explanation):

{
  "canonical_name": "exercise_name_in_snake_case",
  "muscle_data": {
    "muscles": [
      {
        "name": "muscle_name_in_snake_case",
        "role": "primary_mover|synergist|stabilizer",
        "contraction_type": "isotonic|isometric"
      }
    ]
  },
  "primary_mover": "main_muscle_in_snake_case",
  "largest_muscle": "largest_muscle_in_snake_case",
  "movement_archetype": "push|pull|squat|hinge|carry|core",
  "category": "strength|cardio|mobility|plyometric|flexibility",
  "difficulty_level": 1-5,
  "recovery_hours": 24-96
}

Guidelines:
- Use anatomically correct muscle names in snake_case (e.g., pectoralis_major, latissimus_dorsi, rectus_femoris)
- Include all significantly involved muscles (primary movers, synergists, stabilizers)
- Primary movers: muscles doing the main work (isotonic contraction)
- Synergists: muscles assisting the movement (isotonic contraction)
- Stabilizers: muscles maintaining position (isometric contraction)
- Movement archetype: the fundamental movement pattern
- Difficulty: 1=beginner, 2=novice, 3=intermediate, 4=advanced, 5=expert
- Recovery hours: typical time needed between sessions (24-96 hours)
- Category: primary training goal of the exercise

Common muscles to consider:
Upper Body: pectoralis_major, pectoralis_minor, latissimus_dorsi, rhomboids, middle_trapezius, lower_trapezius, upper_trapezius, anterior_deltoid, medial_deltoid, posterior_deltoid, biceps_brachii, triceps_brachii, brachialis, brachioradialis
Lower Body: rectus_femoris, vastus_lateralis, vastus_medialis, vastus_intermedius, biceps_femoris, semitendinosus, semimembranosus, gluteus_maximus, gluteus_medius, gluteus_minimus, gastrocnemius, soleus
Core: rectus_abdominis, external_obliques, internal_obliques, transverse_abdominis, erector_spinae, multifidus

Respond with ONLY the JSON object, no additional text.
PROMPT;
    }

    private function callGemini(string $prompt, string $apiKey, string $model): ?string
    {
        $systemInstruction = 'You are an expert exercise physiologist and biomechanics specialist. Respond only with valid JSON, no markdown formatting or explanations.';
        
        $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";
        
        $this->line("  Sending request to Gemini API...");
        $response = Http::timeout(30)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $systemInstruction . "\n\n" . $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 2048,
            ],
        ]);

        if ($response->successful()) {
            $this->line("  Received response from API");
            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            
            if ($text) {
                $this->line("  Response length: " . strlen($text) . " characters");
                return $text;
            } else {
                $this->warn("  No text content in response");
            }
        }

        // Log error for debugging
        if ($response->failed()) {
            $this->error("  API Error (HTTP {$response->status()}): " . $response->body());
        }

        return null;
    }

    private function parseAIResponse(string $response, Exercise $exercise): ?array
    {
        $this->line("  Cleaning response text...");
        // Clean up response - remove markdown code blocks if present
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = trim($response);

        $this->line("  Decoding JSON...");
        $data = json_decode($response, true);

        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            $this->warn("  JSON decode error: " . json_last_error_msg());
            $this->line("  Raw response: " . substr($response, 0, 200) . "...");
            return null;
        }

        $this->line("  Validating required fields...");
        // Validate required fields
        $required = ['canonical_name', 'muscle_data', 'primary_mover', 'largest_muscle', 
                     'movement_archetype', 'category', 'difficulty_level', 'recovery_hours'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $this->warn("  Missing required field: {$field}");
                return null;
            }
        }

        $muscleCount = count($data['muscle_data']['muscles'] ?? []);
        $this->line("  Found {$muscleCount} muscles in data");

        return $data;
    }

    private function getOutputPath(): string
    {
        return $this->option('output') 
            ? base_path($this->option('output'))
            : storage_path('app/generated_intelligence.json');
    }

    private function saveResults(string $outputPath): void
    {
        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $outputPath,
            json_encode($this->generatedIntelligence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function displaySummary(string $outputPath): void
    {
        $this->info('Generation complete!');
        $this->newLine();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Successfully generated', $this->successCount],
                ['Failed', $this->failureCount],
                ['Total in output file', count($this->generatedIntelligence)],
            ]
        );

        $this->newLine();
        $this->info("Intelligence data saved to: {$outputPath}");
        $this->newLine();
        
        if ($this->successCount > 0) {
            $this->comment('Next steps:');
            $this->line('1. Review the generated JSON file for accuracy');
            $this->line('2. Make any necessary corrections');
            $this->line('3. Sync to database with:');
            $this->line("   php artisan exercises:sync-intelligence --file=" . str_replace(base_path() . '/', '', $outputPath));
        }
    }
}
