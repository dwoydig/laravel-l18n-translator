<div x-init="$store.deeplUsage.load()" x-show="$store.deeplUsage.loaded" class="w-52 text-xs text-gray-500 shrink-0">
    <div class="flex justify-between mb-0.5 gap-2">
        <span>DeepL usage</span>
        <span :class="$store.deeplUsage.overBudget ? 'text-red-600 font-semibold' : ''"
            x-text="$store.deeplUsage.overBudget ? 'over budget' : $store.deeplUsage.percent + '%'"></span>
    </div>
    <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden relative">
        <div class="h-full absolute inset-y-0 left-0 rounded-full transition-all"
            :class="$store.deeplUsage.barColor"
            :style="`width: ${$store.deeplUsage.percent}%`"></div>
        <div x-show="$store.deeplUsage.selectedChars > 0"
            class="h-full absolute inset-y-0 transition-all"
            :class="$store.deeplUsage.overBudget ? 'bg-red-400' : 'bg-purple-400'"
            :style="`left: ${$store.deeplUsage.percent}%; width: ${$store.deeplUsage.selectedPercent}%`"></div>
    </div>
    <div class="mt-0.5 flex justify-between whitespace-nowrap gap-2">
        <span x-text="`${$store.deeplUsage.count.toLocaleString()} / ${$store.deeplUsage.limit.toLocaleString()} chars`"></span>
        <span x-show="$store.deeplUsage.selectedChars > 0"
            :class="$store.deeplUsage.overBudget ? 'text-red-600 font-semibold' : 'text-purple-600'"
            x-text="`+${$store.deeplUsage.selectedChars.toLocaleString()}`"></span>
    </div>
</div>
