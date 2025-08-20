@props(['totals', 'title'])

<div class="nutrition-facts-label">
    <div class="header">
        <h2>{{ $title }}</h2>
    </div>
    <div class="nutrient main">
        <span class="label calories-label">Calories</span>
        <span class="value calories-value">{{ round($totals['calories']) }}</span>
    </div>
    <div class="nutrient main">
        <span class="label">Fat</span>
        <span class="value">{{ round($totals['fats']) }}g</span>
    </div>
    <div class="nutrient main">
        <span class="label">Carbohydrates</span>
        <span class="value">{{ round($totals['carbs']) }}g</span>
    </div>
    <div class="nutrient indented">
        <span class="label">Added Sugars</span>
        <span class="value">{{ round($totals['added_sugars']) }}g</span>
    </div>
    <div class="nutrient indented">
        <span class="label">Fiber</span>
        <span class="value">{{ round($totals['fiber']) }}g</span>
    </div>
    <div class="nutrient main">
        <span class="label">Protein</span>
        <span class="value">{{ round($totals['protein']) }}g</span>
    </div>
    <div class="nutrient main">
        <span class="label">Sodium</span>
        <span class="value">{{ round($totals['sodium']) }}mg</span>
    </div>
    <div class="nutrient">
        <span class="label">Iron</span>
        <span class="value">{{ round($totals['iron']) }}mg</span>
    </div>
    <div class="nutrient">
        <span class="label">Potassium</span>
        <span class="value">{{ round($totals['potassium']) }}mg</span>
    </div>
    <div class="nutrient">
        <span class="label">Calcium</span>
        <span class="value">{{ round($totals['calcium']) }}mg</span>
    </div>
    <div class="nutrient">
        <span class="label">Caffeine</span>
        <span class="value">{{ round($totals['caffeine']) }}mg</span>
    </div>
    <div class="nutrient main cost-nutrient">
        <span class="label">Cost</span>
        <span class="value">${{ number_format($totals['cost'], 2) }}</span>
    </div>
</div>
