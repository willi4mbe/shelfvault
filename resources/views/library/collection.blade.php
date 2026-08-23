@extends('library.layout')

@section('title', $title.' - '.$libraryName)

@section('content')
    <div class="space-y-8">
        <section class="library-page-header">
            <div class="library-page-title">
                <h1>{{ $title }}</h1>
            </div>

            @include('library.partials.listing-controls', [
                'actionUrl' => route($routeName),
                'filters' => $filters,
                'searchReturn' => $searchReturn ?? 'recent',
            ])
        </section>

        @if ($items->count() === 0)
            @include('library.partials.empty', ['filtered' => $filters['search'] !== null || $filters['type'] !== null || $filters['favorite'] || $filters['availability'] !== null || $filters['year'] !== null || $filters['genre'] !== null])
        @else
            <section class="library-poster-grid">
                @foreach ($items as $item)
                    <x-library.poster-card :item="$item" :loans-enabled="$loansEnabled" />
                @endforeach
            </section>

            <div class="library-pagination">
                {{ $items->links() }}
            </div>
        @endif
    </div>
@endsection
