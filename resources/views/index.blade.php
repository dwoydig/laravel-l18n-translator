@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@section('title', 'Languages')

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($availableLanguages as $iso => $name)
    @php $exists = in_array($iso, $existingFiles); @endphp
    <div class="bg-white rounded-lg border border-gray-200 p-4 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="font-medium text-gray-900">{{ $name }}</div>
            <div class="text-xs text-gray-400 font-mono mt-0.5">{{ $iso }}.json</div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if($exists)
                <a href="{{ route('l18n.show', $iso) }}"
                   class="text-xs px-2.5 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                    Edit
                </a>
                <a href="{{ route('l18n.tmx', $iso) }}"
                   class="text-xs px-2.5 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                    TMX
                </a>
            @else
                <span class="text-xs text-gray-400 italic">not created</span>
                <a href="{{ route('l18n.create') }}"
                   class="text-xs px-2.5 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                    Create
                </a>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endsection
