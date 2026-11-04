@props(['name'])
<svg {{ $attributes->merge(['class' => 'icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('home')
<path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z"/>
@break
@case('clients')
<path d="M16 21v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 19.5V21"/>
<circle cx="9.5" cy="8" r="3"/>
<path d="M20 21v-1.5a3.2 3.2 0 0 0-2.4-3.1"/>
<path d="M16.2 5.1a3 3 0 0 1 0 5.8"/>
@break
@case('server')
<rect x="3" y="4" width="18" height="6" rx="1.5"/>
<rect x="3" y="14" width="18" height="6" rx="1.5"/>
<path d="M7 7h.01M7 17h.01"/>
@break
@case('repeat')
<path d="M17 2v4h-4"/>
<path d="M7 22v-4h4"/>
<path d="M19.5 8A7.5 7.5 0 0 0 6.2 6.2L3 9"/>
<path d="M4.5 16a7.5 7.5 0 0 0 13.3 1.8L21 15"/>
@break
@case('list')
<path d="M8 7h12M8 12h12M8 17h12"/>
<path d="M4 7h.01M4 12h.01M4 17h.01"/>
@break
@case('wallet')
<rect x="3" y="6" width="18" height="13" rx="2"/>
<path d="M3 10h18"/>
<path d="M16 14.5h2"/>
@break
@case('more')
<circle cx="6" cy="12" r="1.15" fill="currentColor" stroke="none"/>
<circle cx="12" cy="12" r="1.15" fill="currentColor" stroke="none"/>
<circle cx="18" cy="12" r="1.15" fill="currentColor" stroke="none"/>
@break
@endswitch
</svg>
