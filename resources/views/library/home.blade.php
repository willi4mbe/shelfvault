@extends('library.layout')

@section('title', __('library.meta.title', ['name' => $libraryName]))

@section('content')
    <div class="space-y-8">
        <section class="library-home-dashboard">
            <div class="library-home-intro">
                <p>{{ __('library.hero.eyebrow') }}</p>
                <h1>{{ $libraryName }}</h1>
            </div>
            <form method="GET" action="{{ route('library.search') }}" class="library-home-search library-search-form">
                <input type="hidden" name="from" value="recent">
                <label>
                    <span class="sr-only">{{ __('library.search.label') }}</span>
                    <input type="search" name="q" placeholder="{{ __('library.search.placeholder') }}">
                </label>
                <button type="submit">
                    @include('admin.icon', ['name' => 'search', 'class' => 'h-4 w-4'])
                    <span>{{ __('library.search.submit') }}</span>
                </button>
            </form>
        </section>

        <section class="library-dashboard-block" aria-labelledby="library-dashboard-stats-title">
            <h2 id="library-dashboard-stats-title" class="library-dashboard-heading">{{ __('library.sections.stats') }}</h2>
            <div class="library-dashboard-stats" aria-label="{{ __('library.sections.stats') }}">
                @foreach ($dashboardStats as $stat)
                    <a href="{{ $stat['href'] }}" class="library-dashboard-stat library-dashboard-stat-{{ $stat['key'] }}">
                        <span>{{ $stat['label'] }}</span>
                        <strong>{{ $stat['value'] }}</strong>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="library-section">
            <div class="library-section-heading">
                <div>
                    <h2>{{ __('library.sections.recent') }}</h2>
                    <p>{{ __('library.sections.recent_help') }}</p>
                </div>
                <a href="{{ route('library.recent') }}" class="library-section-link">{{ __('library.actions.view_all') }}</a>
            </div>

            @if ($recentItems->isEmpty())
                @include('library.partials.empty', ['filtered' => false])
            @else
                <div class="library-rail library-recent-rail">
                    @foreach ($recentItems as $item)
                        <x-library.poster-card :item="$item" :loans-enabled="$loansEnabled" />
                    @endforeach
                </div>
            @endif
        </section>

        @if ($loansEnabled && $activeLoanItems->isNotEmpty())
            <section class="library-section">
                <div class="library-section-heading">
                    <div>
                        <h2>{{ __('library.sections.loans') }}</h2>
                    </div>
                    <a href="{{ route('library.loans') }}" class="library-section-link">{{ __('library.actions.view_all') }}</a>
                </div>

                <div class="library-rail library-loans-rail">
                    @foreach ($activeLoanItems as $loan)
                        @include('library.partials.loan-card', ['loan' => $loan, 'dateMode' => 'loaned_at'])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
