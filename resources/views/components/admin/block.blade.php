@props([
    'heading',
    'title',
])

<section x-data="{ open: true }" class="admin-panel admin-block">
    <div class="admin-block-header">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">
                {{ $heading }}
            </p>
            <h2 class="mt-2 text-xl font-semibold tracking-tight text-zinc-950">
                {{ $title }}
            </h2>
        </div>

        <div class="admin-block-controls">
            <button
                type="button"
                class="admin-block-control"
                aria-label="{{ __('admin.dashboard.blocks.move_block') }}"
                title="{{ __('admin.dashboard.blocks.move_block') }}"
            >
                @include('admin.icon', ['name' => 'drag', 'class' => 'admin-block-control-icon'])
            </button>

            <button
                type="button"
                class="admin-block-control"
                x-on:click="open = !open"
                x-bind:aria-label="open ? @js(__('admin.dashboard.blocks.collapse_block')) : @js(__('admin.dashboard.blocks.expand_block'))"
                :title="open ? @js(__('admin.dashboard.blocks.collapse_block')) : @js(__('admin.dashboard.blocks.expand_block'))"
            >
                <span x-show="open" x-cloak>
                    @include('admin.icon', ['name' => 'minimize', 'class' => 'admin-block-control-icon'])
                </span>
                <span x-show="!open" x-cloak>
                    @include('admin.icon', ['name' => 'expand', 'class' => 'admin-block-control-icon'])
                </span>
            </button>
        </div>
    </div>

    <div x-show="open" x-transition class="admin-block-body">
        {{ $slot }}
    </div>
</section>
