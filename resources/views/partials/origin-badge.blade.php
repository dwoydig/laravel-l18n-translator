{{-- Key origin: "app" or the vendor package name. Expects $origin. --}}
<span class="inline-block max-w-full truncate px-1.5 py-0.5 rounded text-xs font-mono {{ $origin === 'app' ? 'bg-gray-100 text-gray-600' : 'bg-indigo-50 text-indigo-700' }}"
      title="{{ $origin === 'app' ? 'Application translation' : 'Vendor package: ' . $origin }}">{{ $origin }}</span>
