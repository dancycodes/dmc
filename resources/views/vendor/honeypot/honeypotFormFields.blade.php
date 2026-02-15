@if($enabled)
    {{--
        BR-144: Honeypot field hidden via CSS positioning (bots detect hidden elements).
        BR-150: Field name is randomized by Spatie Honeypot for additional protection.
        Edge case: aria-hidden + tabindex=-1 prevent screen reader and keyboard access.
    --}}
    <div id="{{ $nameFieldName }}_wrap" style="position: absolute; left: -9999px; top: -9999px; overflow: hidden; height: 0; width: 0;" aria-hidden="true">
        <input id="{{ $nameFieldName }}"
               name="{{ $nameFieldName }}"
               type="text"
               value=""
               autocomplete="nope"
               tabindex="-1">
        <input name="{{ $validFromFieldName }}"
               type="text"
               value="{{ $encryptedValidFrom }}"
               autocomplete="off"
               tabindex="-1">
    </div>
@endif
