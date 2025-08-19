<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DailyLog;
use App\Models\Ingredient;
use Carbon\Carbon;

class DailyLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $csvFile = fopen(database_path("seeders/csv/daily_log_from_real_world.csv"), "r");

        $firstline = true;
        while (($data = fgetcsv($csvFile, 2000, ",")) !== FALSE) {
            if (!$firstline) {
                $ingredientName = $data[2];
                $ingredient = Ingredient::where('name', $ingredientName)->first();

                if ($ingredient) {
                    $loggedAt = Carbon::createFromFormat('m/d/Y H:i', $data[0] . ' ' . $data[1]);
                    
                    DailyLog::create([
                        'ingredient_id' => $ingredient->id,
                        'unit_id' => $ingredient->base_unit_id,
                        'quantity' => $data[4],
                        'logged_at' => $loggedAt,
                        'notes' => $data[3],
                    ]);
                }
            }
            $firstline = false;
        }

        fclose($csvFile);
    }
}
