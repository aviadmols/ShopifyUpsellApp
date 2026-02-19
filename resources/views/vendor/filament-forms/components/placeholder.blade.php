{{-- Override: no x-dynamic-component to avoid "Undefined array key 0" in ManagesComponents when used with Livewire --}}
<div
    {{
        $attributes
            ->merge($getExtraAttributes(), escape: false)
            ->class(['fi-fo-placeholder text-sm leading-6'])
    }}
>
    @php $c = $getContent(); @endphp
    @if(is_array($c))

    @elseif($c instanceof \Illuminate\Contracts\Support\Htmlable)
        {!! $c->toHtml() !!}
    @else
        {{ $c }}
    @endif
</div>
