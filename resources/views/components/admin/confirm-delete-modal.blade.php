{{-- Reusable delete-confirmation modal. Wire a form with the data attributes
     below and this shell intercepts its submit, staying on native form
     submission afterwards:

     <form action="..." method="POST" data-confirm-delete
           data-confirm-title="Delete user"
           data-confirm-message="Permanently remove Jane?">
         @csrf @method('DELETE') ...
     </form>

     One instance per page; the script is idempotent. --}}
@props([])

<div
    id="confirm-delete-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-delete-title"
    class="fixed inset-0 z-50 hidden items-center justify-center p-4"
>
    {{-- Backdrop click dismisses; the card stacks above it and never passes clicks through. --}}
    <div data-confirm-delete-cancel class="absolute inset-0 bg-gray-950/60" aria-hidden="true"></div>

    <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <h2 id="confirm-delete-title" class="text-base font-semibold text-gray-900 dark:text-gray-100">Confirm deletion</h2>
        <p data-confirm-delete-message class="mt-2 text-sm text-gray-600 dark:text-gray-400"></p>

        <div class="mt-6 flex justify-end gap-3">
            <button
                type="button"
                data-confirm-delete-cancel
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:border-gray-400 dark:border-white/20 dark:text-gray-200 dark:hover:border-white/50"
            >
                Cancel
            </button>
            <button
                type="button"
                data-confirm-delete-submit
                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500 dark:bg-red-600 dark:hover:bg-red-500"
            >
                Delete
            </button>
        </div>
    </div>
</div>

<script>
    (() => {
        const modal = document.getElementById('confirm-delete-modal');
        if (!modal) return;

        const title = modal.querySelector('#confirm-delete-title');
        const message = modal.querySelector('[data-confirm-delete-message]');
        const cancel = modal.querySelector('button[data-confirm-delete-cancel]');
        const submit = modal.querySelector('button[data-confirm-delete-submit]');
        const backdrop = modal.querySelector('[data-confirm-delete-cancel]');

        let pendingForm = null;
        let lastTrigger = null;

        function open(form) {
            pendingForm = form;
            lastTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            title.textContent = form.dataset.confirmTitle ?? 'Confirm deletion';
            message.textContent = form.dataset.confirmMessage ?? 'This action cannot be undone.';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            cancel.focus();
        }

        function close() {
            pendingForm = null;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            if (lastTrigger) {
                lastTrigger.focus();
                lastTrigger = null;
            }
        }

        // Intercept submits of every wired delete form and show the modal;
        // the modal's Delete button performs the real POST afterwards.
        document.addEventListener('submit', (event) => {
            const form = event.target instanceof Element
                ? event.target.closest('form[data-confirm-delete]')
                : null;
            if (!form) return;
            event.preventDefault();
            open(form);
        });

        cancel.addEventListener('click', close);
        backdrop.addEventListener('click', close);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                close();
            }
        });

        submit.addEventListener('click', () => {
            const form = pendingForm;
            close();
            if (form) form.submit();
        });
    })();
</script>
