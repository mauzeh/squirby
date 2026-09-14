<!-- DEVICE LIST (bridge to deep-dive) -->
<section class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3 shadow-lg mb-8">
    <div class="flex items-center justify-between border-b border-slate-800 pb-2">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-300">Devices</h2>
        <div class="text-2xs text-slate-400">
            Page <span x-text="summaryData.devices.current_page || 1">1</span> of <span x-text="summaryData.devices.last_page || 1">1</span>
        </div>
    </div>

    <!-- List of Devices -->
    <div class="space-y-2" id="deviceList">
        <template x-if="!summaryData.devices.data || summaryData.devices.data.length === 0">
            <div class="text-center py-6 text-xs text-slate-500">No active devices in selected range.</div>
        </template>

        <template x-for="device in summaryData.devices.data" :key="device.device_id ?? 'null_device'">
            <div 
                @click="openDeepDive(device.device_id)" 
                class="p-2.5 rounded-lg border border-slate-800 hover:border-slate-700 bg-slate-950 cursor-pointer flex flex-col space-y-1 transition-all">
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-1.5 overflow-hidden">
                        <template x-if="device.device_id === null">
                            <span class="text-xs font-mono font-semibold text-amber-400 bg-amber-950/50 border border-amber-800/50 px-1.5 py-0.5 rounded">no value</span>
                        </template>
                        <template x-if="device.device_id !== null">
                            <div class="flex items-center space-x-1 font-mono text-xs text-slate-200 truncate">
                                <span x-text="isDeviceExpanded(device.device_id) ? device.device_id : (device.device_id.substring(0, 8) + '…')"></span>
                                <button 
                                    @click.stop="toggleDeviceExpand(device.device_id)" 
                                    class="text-2xs text-slate-400 hover:text-blue-400 underline ml-1">
                                    <span x-text="isDeviceExpanded(device.device_id) ? '[less]' : '[full]'"></span>
                                </button>
                            </div>
                        </template>
                    </div>
                    
                    <span class="text-2xs bg-slate-800 text-slate-300 font-semibold px-2 py-0.5 rounded-full" x-text="(device.event_rows || 0) + ' events'"></span>
                </div>

                <div class="flex items-center justify-between text-2xs text-slate-400 pt-0.5">
                    <span>Last active: <span x-text="formatDate(device.last_seen)"></span></span>
                    <span class="text-blue-400">Deep dive &rarr;</span>
                </div>
            </div>
        </template>
    </div>

    <!-- Pagination Controls -->
    <div class="flex items-center justify-between pt-2 border-t border-slate-800">
        <button 
            @click="changePage(summaryData.devices.current_page - 1)" 
            :disabled="summaryData.devices.current_page <= 1"
            :class="summaryData.devices.current_page <= 1 ? 'opacity-40 cursor-not-allowed bg-slate-800 text-slate-500' : 'bg-slate-800 text-slate-200 hover:bg-slate-700'"
            class="px-3 py-1 text-xs rounded transition-colors">
            &larr; Prev
        </button>
        <span class="text-2xs text-slate-400" x-text="(summaryData.devices.total || 0) + ' total devices'"></span>
        <button 
            @click="changePage(summaryData.devices.current_page + 1)" 
            :disabled="summaryData.devices.current_page >= summaryData.devices.last_page"
            :class="summaryData.devices.current_page >= summaryData.devices.last_page ? 'opacity-40 cursor-not-allowed bg-slate-800 text-slate-500' : 'bg-slate-800 text-slate-200 hover:bg-slate-700'"
            class="px-3 py-1 text-xs rounded transition-colors">
            Next &rarr;
        </button>
    </div>
</section>
