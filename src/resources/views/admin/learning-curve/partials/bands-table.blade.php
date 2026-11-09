{{-- props: bands (Collection of LearningCurveBand), editable (bool) --}}
<div class="table-responsive">
    <table class="table table-bordered table-sm text-center align-middle mb-0">
        <thead>
            <tr>
                <th style="min-width:90px">SMV Range</th>
                <th style="min-width:70px">Ref. SMV</th>
                @for($d = 1; $d <= 10; $d++)
                    <th>Day {{ $d }}</th>
                @endfor
                @if($editable)
                    <th style="width:70px"></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($bands as $band)
                <tr>
                    @if($editable)
                        <form id="band-form-{{ $band->id }}" method="POST" action="{{ route('msfl.learning-curve.update', $band) }}">
                            @csrf @method('PUT')
                        </form>
                    @endif
                    <td class="font-weight-bold">{{ $band->label }}</td>
                    <td>{{ $band->reference_smv }}</td>
                    @foreach($band->day_percents as $i => $pct)
                        <td>
                            @if($editable)
                                <input type="number" step="0.01" min="0" max="100" name="day_percents[]" value="{{ $pct }}"
                                    form="band-form-{{ $band->id }}" class="form-control form-control-sm text-center" style="min-width:60px">
                            @else
                                {{ $pct }}%
                            @endif
                        </td>
                    @endforeach
                    @if($editable)
                        <td><button type="submit" form="band-form-{{ $band->id }}" class="btn btn-sm btn-icon ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i></button></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
