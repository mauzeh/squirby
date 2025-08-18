<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Services\NutritionService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    protected $nutritionService;

    public function __construct(NutritionService $nutritionService)
    {
        $this->nutritionService = $nutritionService;
    }

    public function showExportForm()
    {
        return view('daily_logs.export');
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $dailyLogs = DailyLog::with(['ingredient', 'unit'])
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->orderBy('logged_at', 'desc')
            ->get();

        $fileName = 'daily_log_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.csv';
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = array('Date', 'Time', 'Ingredient', 'Quantity', 'Unit', 'Calories', 'Protein (g)', 'Carbs (g)', 'Fats (g)', 'Added Sugars (g)', 'Sodium (mg)', 'Iron (mg)', 'Potassium (mg)', 'Cost');

        $callback = function() use($dailyLogs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($dailyLogs as $log) {
                $row['Date']  = $log->logged_at->format('Y-m-d');
                $row['Time']  = $log->logged_at->format('H:i');
                $row['Ingredient']    = $log->ingredient->name;
                $row['Quantity']    = $log->quantity;
                $row['Unit']  = $log->unit->name;
                $row['Calories'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calories', $log->quantity));
                $row['Protein (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'protein', $log->quantity), 1);
                $row['Carbs (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'carbs', $log->quantity), 1);
                $row['Fats (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'fats', $log->quantity), 1);
                $row['Added Sugars (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'added_sugars', $log->quantity), 1);
                $row['Sodium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'sodium', $log->quantity), 1);
                $row['Iron (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'iron', $log->quantity), 1);
                $row['Potassium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'potassium', $log->quantity), 1);
                $row['Cost'] = number_format($this->nutritionService->calculateCostForQuantity($log->ingredient, $log->quantity), 2);

                fputcsv($file, array($row['Date'], $row['Time'], $row['Ingredient'], $row['Quantity'], $row['Unit'], $row['Calories'], $row['Protein (g)'], $row['Carbs (g)'], $row['Fats (g)'], $row['Added Sugars (g)'], $row['Sodium (mg)'], $row['Iron (mg)'], $row['Potassium (mg)'], $row['Cost']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportAll(Request $request)
    {
        $dailyLogs = DailyLog::with(['ingredient', 'unit'])
            ->orderBy('logged_at', 'desc')
            ->get();

        $fileName = 'daily_log_all.csv';
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = array('Date', 'Time', 'Ingredient', 'Quantity', 'Unit', 'Calories', 'Protein (g)', 'Carbs (g)', 'Fats (g)', 'Added Sugars (g)', 'Sodium (mg)', 'Iron (mg)', 'Potassium (mg)', 'Cost');

        $callback = function() use($dailyLogs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($dailyLogs as $log) {
                $row['Date']  = $log->logged_at->format('Y-m-d');
                $row['Time']  = $log->logged_at->format('H:i');
                $row['Ingredient']    = $log->ingredient->name;
                $row['Quantity']    = $log->quantity;
                $row['Unit']  = $log->unit->name;
                $row['Calories'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'calories', $log->quantity));
                $row['Protein (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'protein', $log->quantity), 1);
                $row['Carbs (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'carbs', $log->quantity), 1);
                $row['Fats (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'fats', $log->quantity), 1);
                $row['Added Sugars (g)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'added_sugars', $log->quantity), 1);
                $row['Sodium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'sodium', $log->quantity), 1);
                $row['Iron (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'iron', $log->quantity), 1);
                $row['Potassium (mg)'] = round($this->nutritionService->calculateTotalMacro($log->ingredient, 'potassium', $log->quantity), 1);
                $row['Cost'] = number_format($this->nutritionService->calculateCostForQuantity($log->ingredient, $log->quantity), 2);

                fputcsv($file, array($row['Date'], $row['Time'], $row['Ingredient'], $row['Quantity'], $row['Unit'], $row['Calories'], $row['Protein (g)'], $row['Carbs (g)'], $row['Fats (g)'], $row['Added Sugars (g)'], $row['Sodium (mg)'], $row['Iron (mg)'], $row['Potassium (mg)'], $row['Cost']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
