@extends('install.layout', ['currentStep' => 'admin'])

@section('content')
    <form method="POST" action="{{ route('install.complete') }}" class="install-content-panel flex h-full min-h-0 w-full flex-col overflow-hidden rounded-[28px]">
        @csrf

        <div class="install-card-header p-4 sm:p-5 lg:px-6 lg:py-5">
            <h2 class="text-[1.35rem] font-semibold leading-tight text-zinc-950 sm:text-[1.65rem]">{{ __('install.admin.title') }}</h2>
            <p class="mt-2 max-w-xl text-sm leading-6 text-zinc-600">{{ __('install.admin.intro') }}</p>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5 lg:px-6">
            @if ($errors->any())
                <div class="install-alert px-4 py-3 text-sm font-semibold">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 {{ $errors->any() ? 'mt-5' : '' }}">
                <label class="block">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.login') }}</span>
                    <input name="login" value="{{ old('login') }}" autocomplete="username" class="install-input mt-2">
                </label>

                <label class="block">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.email') }}</span>
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" class="install-input mt-2">
                </label>

                <label class="block">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.password') }}</span>
                    <input type="password" name="password" autocomplete="new-password" class="install-input mt-2">
                    <span class="mt-2 block text-sm text-zinc-500">{{ __('install.admin.password_help') }}</span>
                </label>

                <label class="block">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.password_confirmation') }}</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" class="install-input mt-2">
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.admin_language') }}</span>
                    <select name="preferred_locale" class="install-input mt-2">
                        @foreach ($locales as $code => $label)
                            <option value="{{ $code }}" @selected(old('preferred_locale', 'en') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="mt-6 border-t border-zinc-200/80 pt-5">
                <h3 class="text-lg font-semibold text-zinc-950">{{ __('install.admin.settings_title') }}</h3>
                <p class="mt-1 text-sm leading-6 text-zinc-600">{{ __('install.admin.settings_intro') }}</p>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.app_name') }}</span>
                    <input name="app_name" value="{{ old('app_name', $settings['app_name']) }}" class="install-input mt-2">
                </label>

                <label class="block">
                    <span class="text-sm font-bold text-zinc-700">{{ __('install.fields.app_url') }}</span>
                    <input name="app_url" value="{{ old('app_url', $settings['app_url']) }}" inputmode="url" class="install-input mt-2">
                </label>

            </div>
        </div>

        <div class="mt-auto shrink-0 border-t border-zinc-200/80 px-4 py-4 sm:px-5 sm:py-5 lg:px-6">
            <button type="submit" class="install-button-primary inline-flex w-full items-center justify-center px-6 text-sm font-bold sm:w-auto">
                {{ __('install.admin.submit') }}
            </button>
        </div>
    </form>
@endsection
