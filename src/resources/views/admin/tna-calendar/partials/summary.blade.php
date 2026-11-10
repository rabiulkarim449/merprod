{{-- The four counters on top of the client's sheet. props: summary --}}
<div class="tna-summary">
    @foreach($summary as $label => $count)
        <div class="tna-summary-item">
            <span class="k">{{ $label }}</span><span class="v">{{ number_format($count) }}</span>
        </div>
    @endforeach
</div>
