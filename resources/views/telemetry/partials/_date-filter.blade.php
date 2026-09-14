<!-- Date filter (trimmed to 5 presets + custom) -->
<section class="space-y-2">
    <div class="text-2xs font-semibold uppercase tracking-wider text-slate-500">Date range</div>
    <div class="flex flex-wrap gap-1.5">
        <template x-for="chip in chips" :key="chip.id">
            <button 
                @click="setChip(chip.id)" 
                :class="activeChip === chip.id ? 'bg-blue-600 text-white font-medium shadow' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
                class="chip px-3 py-1 text-xs rounded-full transition-colors">
                <span x-text="chip.label"></span>
            </button>
        </template>
        <button 
            @click="activeChip = 'custom'" 
            :class="activeChip === 'custom' ? 'bg-blue-600 text-white font-medium shadow' : 'bg-slate-800 text-slate-400 hover:bg-slate-700'"
            class="px-3 py-1 text-xs rounded-full transition-colors">
            Custom…
        </button>
    </div>
    <div x-show="activeChip === 'custom'" class="pt-1">
        <input 
            type="date" 
            x-model="customDate" 
            @change="onDateInput()" 
            class="bg-slate-900 border border-slate-800 text-slate-200 text-xs rounded px-2 py-1 w-full focus:ring-1 focus:ring-blue-500 focus:outline-none">
    </div>
</section>
