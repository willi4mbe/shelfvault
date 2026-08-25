<section class="library-stats" aria-label="{{ __('library.sections.stats') }}">
    @foreach ($stats as $key => $value)
        <div class="library-stat">
            <span>{{ __('library.stats.'.$key) }}</span>
            <strong>{{ $value }}</strong>
        </div>
    @endforeach
</section>
