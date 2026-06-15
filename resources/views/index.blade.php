@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@section('title', 'Languages')

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($languageFiles as $file)
    <a href="{{ route('l18n.show', $file->filename) }}"
       class="bg-white rounded-lg border border-gray-200 p-4 flex items-center gap-3 hover:border-blue-400 hover:shadow-sm transition-all">
        <span class="text-2xl leading-none">{{ $file->flag }}</span>
        <div class="min-w-0">
            <div class="font-medium text-gray-900">{{ $file->name }}</div>
            <div class="text-xs text-gray-400 font-mono mt-0.5">{{ $file->basename }}</div>
        </div>
    </a>
    @empty
    <p class="text-gray-500 col-span-3">No language files found in <code>resources/lang/</code>.</p>
    @endforelse
</div>
@endsection
