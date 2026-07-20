<div class="flex items-center gap-3 mb-2.5">
    @if($canSelect)
    <input type="checkbox"
        @click.stop
        @change="toggleRow('{{ $stat['file']->filename }}')"
        :checked="selected.has('{{ $stat['file']->filename }}')"
        class="rounded border-gray-300 text-blue-600 cursor-pointer">
    @endif
    <span class="text-xl leading-none">{{ $stat['file']->flag }}</span>
    <div class="min-w-0 flex-1">
        <span class="font-medium text-gray-900 text-sm">{{ $stat['file']->name }}</span>
        <span class="text-xs text-gray-400 font-mono ml-2">{{ $stat['file']->basename }}</span>
    </div>
    <span class="text-base font-bold tabular-nums {{ $pctColor }}">{{ $pct }}%</span>
</div>

{{-- Block visualisation --}}
<div class="flex gap-px mb-2" title="{{ $stat['translated'] }} translated / {{ $stat['missing'] }} missing">
    @for($i = 0; $i < $greenBlocks; $i++)
    <div class="h-4 flex-1 rounded-sm bg-green-400"></div>
    @endfor
    @for($i = 0; $i < $redBlocks; $i++)
    <div class="h-4 flex-1 rounded-sm bg-red-400"></div>
    @endfor
</div>

{{-- Stats --}}
<div class="flex flex-wrap gap-x-4 gap-y-0.5 text-xs">
    <span class="text-green-700 font-medium">&#10003; {{ $stat['translated'] }} translated</span>
    <span class="text-red-600 font-medium">&#10007; {{ $stat['missing'] }} missing</span>
    @if($stat['missing'] > 0)
    <span class="text-gray-400">({{ $stat['missingChars'] }} chars)</span>
    @endif
    @if($stat['orphaned'] > 0)
    <span class="text-amber-600 font-medium">&#9888; {{ $stat['orphaned'] }} orphaned</span>
    @endif
</div>
