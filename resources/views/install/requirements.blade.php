@extends('install.layout', ['currentStep' => 'requirements'])

@section('content')
    <div class="install-content-panel flex h-full min-h-0 w-full flex-col overflow-hidden rounded-[28px]">
        <div class="install-card-header flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5 lg:px-6 lg:py-5">
            <div>
                <h2 class="text-[1.35rem] font-semibold leading-tight text-zinc-950 sm:text-[1.65rem]">{{ __('install.requirements.title') }}</h2>
                <p class="mt-2 max-w-xl text-sm leading-6 text-zinc-600">{{ __('install.requirements.intro') }}</p>
            </div>
            <span class="inline-flex w-fit whitespace-nowrap rounded-full px-3.5 py-2 text-sm font-bold {{ $passes ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' }}">
                {{ $passes ? __('install.requirements.ready_short') : __('install.requirements.blocked') }}
            </span>
        </div>

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5 lg:px-6">
            <section>
                <h3 class="text-xs font-bold uppercase tracking-[0.08em] text-zinc-500">{{ __('install.requirements.system') }}</h3>
                <div class="install-list mt-2">
                    @foreach ($requirements as $requirement)
                        <div class="install-list-row {{ $requirement['kind'] === 'version' ? 'grid-cols-[1fr_auto] sm:grid-cols-[1fr_7.5rem_auto]' : 'grid-cols-[1fr_auto]' }}">
                            <div>
                                <p class="font-semibold text-zinc-950">{{ $requirement['name'] }}</p>
                                @if ($requirement['kind'] === 'version')
                                    <p class="mt-1 text-sm text-zinc-500 sm:hidden">{{ $requirement['current'] }} / {{ $requirement['required'] }}</p>
                                @endif
                            </div>
                            @if ($requirement['kind'] === 'version')
                                <p class="hidden text-sm font-medium text-zinc-500 sm:block">{{ $requirement['current'] }} / {{ $requirement['required'] }}</p>
                            @endif
                            <span class="install-status self-start {{ $requirement['passes'] ? 'install-status-pass' : 'install-status-fail' }}">
                                {{ $requirement['kind'] === 'version'
                                    ? ($requirement['passes'] ? __('install.status.passed') : __('install.status.failed'))
                                    : ($requirement['passes'] ? __('install.status.installed') : __('install.status.missing')) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section>
                <h3 class="text-xs font-bold uppercase tracking-[0.08em] text-zinc-500">{{ __('install.requirements.writable') }}</h3>
                <div class="install-list mt-2">
                    @foreach ($writablePaths as $path)
                        <div class="install-list-row grid-cols-[1fr_auto]">
                            <div class="min-w-0">
                                <p class="font-semibold text-zinc-950">{{ $path['name'] }}</p>
                                <p class="mt-1 truncate text-sm text-zinc-500">{{ $path['path'] }}</p>
                            </div>
                            <span class="install-status self-start {{ $path['passes'] ? 'install-status-pass' : 'install-status-fail' }}">
                                {{ $path['passes'] ? __('install.status.passed') : __('install.status.failed') }}
                            </span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-sm leading-6 text-zinc-500">{{ __('install.requirements.storage_note') }}</p>
            </section>
        </div>

        <div class="mt-auto shrink-0 border-t border-zinc-200/80 px-4 py-4 sm:px-5 sm:py-5 lg:px-6">
            @if ($passes)
                <a href="{{ route('install.database') }}" class="install-button-primary inline-flex w-full items-center justify-center px-6 text-sm font-bold sm:w-auto">
                    {{ __('install.requirements.continue') }}
                </a>
            @else
                <button type="button" disabled class="install-button-disabled inline-flex w-full cursor-not-allowed items-center justify-center px-6 text-sm font-bold sm:w-auto">
                    {{ __('install.requirements.continue') }}
                </button>
            @endif
        </div>
    </div>
@endsection
