@extends('library.layout')

@section('title', __('library.navigation.loans').' - '.$libraryName)

@section('content')
    <div class="space-y-8">
        <section class="library-page-header">
            <div class="library-page-title">
                <h1>{{ __('library.sections.loans') }}</h1>
            </div>
        </section>

        @if ($activeLoans->count() === 0)
            <div class="library-empty">{{ __('library.empty.no_loans') }}</div>
        @else
            <section class="library-poster-grid">
                @foreach ($activeLoans as $loan)
                    @include('library.partials.loan-card', ['loan' => $loan])
                @endforeach
            </section>

            <div class="library-pagination">
                {{ $activeLoans->links() }}
            </div>
        @endif
    </div>
@endsection
