@extends('admin.layout')

@section('title', __('admin.auth.page_title'))

@section('sidebar')
    <div class="rounded-[24px] border border-white/10 bg-white/6 p-4 text-sm leading-6 text-white/72">
        <p class="font-medium text-white/86">{{ __('admin.auth.sidebar_title') }}</p>
        <p class="mt-2">
            {{ __('admin.auth.sidebar_note') }}
        </p>
    </div>
@endsection

@section('content')
    <div class="mx-auto flex h-full max-w-2xl items-center">
        <section class="w-full rounded-[28px] border border-zinc-200/80 bg-white p-5 shadow-[0_24px_65px_rgba(9,9,11,0.08)] sm:p-6 lg:p-7">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">
                {{ __('admin.auth.badge') }}
            </p>
            <h1 class="mt-3 text-[1.9rem] font-semibold leading-tight tracking-tight text-zinc-950 sm:text-[2.35rem]">
                {{ __('admin.auth.title') }}
            </h1>
            <p class="mt-3 max-w-lg text-sm leading-6 text-zinc-600 sm:text-[0.98rem]">
                {{ __('admin.auth.subtitle') }}
            </p>

            @if ($errors->has('identifier'))
                <div class="install-alert mt-5 p-4 text-sm leading-6">
                    {{ $errors->first('identifier') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}" class="mt-6 space-y-4">
                @csrf

                <label class="block">
                    <span class="text-sm font-medium text-zinc-700">{{ __('admin.auth.identifier_label') }}</span>
                    <input
                        type="text"
                        name="identifier"
                        value="{{ old('identifier') }}"
                        autocomplete="username"
                        class="install-input mt-2"
                    >
                    <span class="mt-2 block text-xs leading-5 text-zinc-500">
                        {{ __('admin.auth.identifier_help') }}
                    </span>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-zinc-700">{{ __('admin.auth.password_label') }}</span>
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        class="install-input mt-2"
                    >
                </label>

                <button type="submit" class="install-button-primary w-full rounded-full px-5 py-3 text-sm font-semibold">
                    {{ __('admin.auth.submit') }}
                </button>
            </form>
        </section>
    </div>
@endsection
