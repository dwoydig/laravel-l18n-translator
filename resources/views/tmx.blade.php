{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<!DOCTYPE tmx SYSTEM "tmx14.dtd">
<tmx version="1.4">
  <header segtype="sentence" o-tmf="UTF-8" adminlang="{{ $mainLanguageIso }}" srclang="{{ $mainLanguageIso }}" datatype="PlainText"/>
  <body>
    @foreach($translations as $entry)
    @if($entry->translation)
    <tu creationtool="l18n-translator" tuid="{{ md5($entry->key) }}">
      <tuv xml:lang="{{ $lang }}">
        <seg>{{ $entry->translation }}</seg>
      </tuv>
      <tuv xml:lang="{{ $mainLanguageIso }}">
        <seg>{{ $entry->original }}</seg>
      </tuv>
    </tu>
    @endif
    @endforeach
  </body>
</tmx>
