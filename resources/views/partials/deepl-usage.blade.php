<div class="flex justify-between mb-1.5 gap-2">
    <span class="font-medium text-gray-700">DeepL Usage</span>
    <span :class="$store.deeplUsage.overBudget ? 'text-red-600 font-semibold' : ''"
        x-text="$store.deeplUsage.overBudget ? 'over budget' : $store.deeplUsage.percent + '%'"></span>
</div>
<div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden relative">
    <div class="h-full absolute inset-y-0 left-0 rounded-full transition-all"
        :class="$store.deeplUsage.barColor"
        :style="`width: ${$store.deeplUsage.percent}%`"></div>
    <div x-show="$store.deeplUsage.selectedChars > 0"
        class="h-full absolute inset-y-0 transition-all"
        :class="$store.deeplUsage.overBudget ? 'bg-red-400' : 'bg-purple-400'"
        :style="`left: ${$store.deeplUsage.percent}%; width: ${$store.deeplUsage.selectedPercent}%`"></div>
</div>
<div class="mt-1.5 flex justify-between whitespace-nowrap gap-2">
    <span x-text="`${$store.deeplUsage.count.toLocaleString()} / ${$store.deeplUsage.limit.toLocaleString()} chars`"></span>
    <span x-show="$store.deeplUsage.selectedChars > 0"
        :class="$store.deeplUsage.overBudget ? 'text-red-600 font-semibold' : 'text-purple-600'"
        x-text="`+${$store.deeplUsage.selectedChars.toLocaleString()}`"></span>
</div>
