@extends(config('l18n-translator.layout') ?? 'l18n-translator::layout')

@section('title', 'Create New Language')

@section('content')
<div class="max-w-md bg-white border border-gray-200 rounded-lg p-6">
    <form method="POST" action="{{ route('l18n.store') }}">
        @csrf
        <div class="mb-4">
            <label for="targetLanguage" class="block text-sm font-medium text-gray-700 mb-1">
                Target Language
            </label>
            <select id="targetLanguage" name="targetLanguage" required
                class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">— select a language —</option>
                @foreach($availableLanguages as $iso => $name)
                    @if(!in_array($iso, $existingFiles))
                        <option value="{{ $iso }}">{{ $name }} ({{ $iso }})</option>
                    @endif
                @endforeach
            </select>
            <p class="mt-1.5 text-xs text-gray-500">
                An empty <code class="font-mono">{lang}.json</code> will be created with all keys from
                <code class="font-mono">{{ config('l18n-translator.main_language', 'en') }}.json</code>.
                Use the editor to fill in translations manually or via DeepL.
            </p>
        </div>
        <button type="submit"
            class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">
            Create language file
        </button>
    </form>
</div>
@endsection
