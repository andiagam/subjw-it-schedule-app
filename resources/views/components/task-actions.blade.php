@props(['task', 'label'])

<div class="flex shrink-0 items-center">
    <button
        type="button"
        aria-label="Edit"
        title="Edit"
        class="inline-flex size-8 items-center justify-center rounded-md text-current hover:bg-white/20"
        x-on:click="openEdit(@js(['id' => $task->id, 'date' => $task->date->toDateString(), 'label' => $label, 'title' => $task->title, 'kind' => $task->kind, 'done' => (bool) $task->is_done]))"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
        </svg>
    </button>
    <form method="POST" action="{{ route('roster.tasks.destroy', $task) }}" onsubmit="return confirm('Remove this from the calendar?')">
        @csrf
        @method('DELETE')
        <button type="submit" aria-label="Remove" title="Remove" class="inline-flex size-8 items-center justify-center rounded-md text-current hover:bg-white/20">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path d="M18 6 6 18" />
                <path d="m6 6 12 12" />
            </svg>
        </button>
    </form>
</div>
