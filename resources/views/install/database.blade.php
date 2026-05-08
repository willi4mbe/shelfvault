@extends('install.layout', ['currentStep' => 'database'])

@section('content')
    <form method="POST" action="{{ route('install.database.store') }}" class="install-content-panel flex h-full min-h-0 w-full flex-col overflow-hidden rounded-[28px]">
        @csrf

        <div class="install-card-header p-4 sm:p-5 lg:px-6 lg:py-5">
            <h2 class="text-[1.35rem] font-semibold leading-tight text-zinc-950 sm:text-[1.65rem]">{{ __('install.database.title') }}</h2>
            <p class="mt-2 max-w-xl text-sm leading-6 text-zinc-600">{{ __('install.database.intro') }}</p>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5 lg:px-6">
            @if ($errors->any())
                <div class="install-alert px-4 py-3 text-sm font-semibold">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 {{ $errors->any() ? 'mt-5' : '' }}">
                <label class="block sm:col-span-2">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.connection') }}</span>
                    <select name="connection" class="install-input mt-2">
                        <option value="mysql" @selected(old('connection', $database['connection']) === 'mysql')>MySQL / MariaDB</option>
                        <option value="pgsql" @selected(old('connection', $database['connection']) === 'pgsql')>PostgreSQL</option>
                    </select>
                    <span class="mt-2 block text-sm text-zinc-500">{{ __('install.database.connection_help') }}</span>
                </label>

                <label class="block">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.host') }}</span>
                    <input name="host" value="{{ old('host', $database['host']) }}" autocomplete="off" class="install-input mt-2">
                </label>

                <label class="block">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.port') }}</span>
                    <input name="port" value="{{ old('port', $database['port']) }}" inputmode="numeric" autocomplete="off" class="install-input mt-2">
                </label>

                <label class="block">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.database') }}</span>
                    <input name="database" value="{{ old('database', $database['database']) }}" autocomplete="off" class="install-input mt-2">
                </label>

                <label class="block">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.username') }}</span>
                    <input name="username" value="{{ old('username', $database['username']) }}" autocomplete="username" class="install-input mt-2">
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.password') }}</span>
                    <input type="password" name="password" autocomplete="current-password" class="install-input mt-2">
                </label>
            </div>
        </div>

        <div class="mt-auto shrink-0 border-t border-zinc-200/80 px-4 py-4 sm:px-5 sm:py-5 lg:px-6">
            <button type="submit" class="install-button-primary inline-flex w-full items-center justify-center px-6 text-sm font-bold sm:w-auto">
                {{ __('install.database.submit') }}
            </button>
        </div>
    </form>
@endsection
