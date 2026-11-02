@php
    use Illuminate\Support\Str;
@endphp

<x-layouts.portal title="Track {{ $complaint->ticket_number }} — HT-CGRF">
    {{-- Case header --}}
    <section aria-labelledby="case-heading" class="mb-8">
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="inline-flex items-center rounded-md bg-gray-900 px-2.5 py-1 font-mono text-xs font-semibold tracking-wider text-white dark:bg-white dark:text-gray-900"
            >
                {{ $complaint->ticket_number }}
            </span>
            <x-complaint.status-badge :complaint="$complaint" />
        </div>

        <h1 id="case-heading" class="mt-3 text-2xl font-bold text-gray-900 dark:text-gray-100">
            {{ $complaint->subject }}
        </h1>
    </section>

    @if (session('status'))
        <div class="mb-6 rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-6">
        {{-- Progress pipeline stays first: the visitor reads status before details. --}}
        <section
            aria-labelledby="progress-heading"
            class="rounded-lg bg-white p-6 ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
        >
            <h2 id="progress-heading" class="mb-6 text-base font-semibold text-gray-900 dark:text-gray-100">
                Progress pipeline
            </h2>

            <x-complaint.stepper :complaint="$complaint" />
        </section>

        {{-- Case details --}}
        <section
            aria-labelledby="details-heading"
            class="rounded-lg bg-white p-6 ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
        >
            <h2 id="details-heading" class="mb-4 text-base font-semibold text-gray-900 dark:text-gray-100">
                Case details
            </h2>

            <dl class="grid gap-x-8 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Consumer</dt>
                    <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ $complaint->consumer->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Consumer No.</dt>
                    <dd class="mt-0.5 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $complaint->consumer_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Category</dt>
                    <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ Str::headline($complaint->category->value) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Region</dt>
                    <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ $complaint->region }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Lodged on</dt>
                    <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ $complaint->created_at->format('d M Y, h:i A') }}</dd>
                </div>
                @if ($complaint->resolved_at !== null)
                    <div>
                        <dt class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Resolved on</dt>
                        <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ $complaint->resolved_at->format('d M Y, h:i A') }}</dd>
                    </div>
                @endif
            </dl>

            @php
                $consumerForDetails = $complaint->consumer;

                $consumerDetails = [
                    'Email'         => $consumerForDetails->email,
                    'Mobile'        => $consumerForDetails->mobile,
                    'Load'          => $consumerForDetails->load,
                    'Meter No.'     => $consumerForDetails->meter_number,
                    'Tariff'        => $consumerForDetails->tariff,
                    'Region'        => $consumerForDetails->region,
                    'DISCOM'        => $consumerForDetails->discom?->name,
                    'Circle'        => $consumerForDetails->circle?->name,
                    'Division'      => $consumerForDetails->division?->name,
                    'Subdivision'   => $consumerForDetails->subdivision?->name,
                    'Section'       => $consumerForDetails->section?->name,
                ];
            @endphp

            <h3 class="mt-6 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Consumer details</h3>
            <dl class="mt-3 grid gap-x-8 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Name</dt>
                    <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ $consumerForDetails->name }}</dd>
                </div>
                @foreach ($consumerDetails as $label => $value)
                    <div>
                        <dt class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm {{ filled($value) ? 'text-gray-900 font-mono dark:text-gray-100' : 'text-gray-400 dark:text-gray-600' }}">
                            {{ filled($value) ? $value : '-' }}
                        </dd>
                    </div>
                @endforeach
                @if (filled($consumerForDetails->address))
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Address</dt>
                        <dd class="mt-0.5 text-sm whitespace-pre-line text-gray-900 dark:text-gray-100">{{ $consumerForDetails->address }}</dd>
                    </div>
                @endif
            </dl>

            <h3 class="mt-6 text-xs font-bold tracking-wide text-gray-500 uppercase dark:text-gray-400">Complaint details</h3>
            <p class="mt-2 rounded-md border border-gray-300 bg-gray-50 p-4 text-sm leading-relaxed text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                {{ $complaint->description }}
            </p>

            @php
                $consumerAttachments = $complaint->attachments ?? [];
                $authorityAttachments = $complaint->authority_attachments ?? [];
            @endphp

            @if (filled($consumerAttachments))
                <h3 class="mt-6 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Attachments by consumer ({{ count($consumerAttachments) }})
                </h3>
                <x-complaint.attachments :paths="$consumerAttachments" class="mt-2" />
            @endif

            @if (filled($authorityAttachments))
                <h3 class="mt-6 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Attachments by Competent Authority ({{ count($authorityAttachments) }})
                </h3>
                <x-complaint.attachments :paths="$authorityAttachments" class="mt-2" />
            @endif
        </section>

        @if (auth()->user()?->role === \App\Enums\UserRole::RegionalManager
            && (int) $complaint->regional_manager_id === (int) auth()->id()
            && $complaint->status === \App\Enums\ComplaintStatus::Submitted)
            <section class="rounded-lg bg-white p-6 ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Review and forward</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">After reviewing this complaint, select a Competent Authority in the same division.</p>

                @if ($competentAuthorities->isEmpty())
                    <p class="mt-4 text-sm text-amber-700 dark:text-amber-400">No Competent Authority is assigned to this division yet.</p>
                @else
                    <form method="POST" action="{{ route('complaints.forward', $complaint) }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <label for="competent_authority_id" class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100">Competent Authority</label>
                            <select id="competent_authority_id" name="competent_authority_id" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">Select Competent Authority</option>
                                @foreach ($competentAuthorities as $authority)
                                    <option value="{{ $authority->id }}" @selected(old('competent_authority_id') == $authority->id)>{{ $authority->name }}</option>
                                @endforeach
                            </select>
                            @error('competent_authority_id')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="forward_comment" class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100">Review comment (optional)</label>
                            <textarea id="forward_comment" name="comment" rows="3" maxlength="5000" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-gray-100">{{ old('comment') }}</textarea>
                            @error('comment')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600">Forward to Competent Authority</button>
                    </form>
                @endif
            </section>
        @endif

        {{-- Audit trail timeline --}}
        <section
            aria-labelledby="history-heading"
            class="rounded-lg bg-white p-6 ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10"
        >
            <h2 id="history-heading" class="mb-5 text-base font-semibold text-gray-900 dark:text-gray-100">
                Case history
            </h2>

            <x-complaint.timeline :actions="$complaint->actions" :complaint="$complaint" />
        </section>

        {{-- Resolve and close: the assigned competent authority settles the case --}}
        @if (auth()->user()?->role === \App\Enums\UserRole::CompetentAuthority
            && (int) $complaint->competent_authority_id === (int) auth()->id()
            && $complaint->status === \App\Enums\ComplaintStatus::Forwarded)
            <section class="rounded-lg bg-white p-6 ring-1 ring-gray-200 shadow-sm dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Resolve and close</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Add final remarks and supporting files, then close this complaint.</p>

                <form method="POST" action="{{ route('complaints.resolve', $complaint) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="resolve_comment" class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100">Resolution remarks</label>
                        <textarea id="resolve_comment" name="comment" rows="3" maxlength="5000" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-gray-100">{{ old('comment') }}</textarea>
                        @error('comment')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100">Attachments (optional, up to 5 files)</label>

                        <div id="resolve-attachment-rows" class="space-y-2">
                            <div data-attachment-row class="flex items-center gap-2">
                                <input
                                    type="file"
                                    name="attachments[]"
                                    accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.mp4,.avi,.mov,.webm"
                                    class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-blue-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white dark:border-white/20 dark:bg-gray-800 dark:file:bg-blue-700 dark:text-gray-100 dark:file:text-white"
                                />
                                <button
                                    type="button"
                                    data-remove-row
                                    hidden
                                    class="shrink-0 rounded-md border border-red-300 px-2.5 py-2 text-sm text-red-600 hover:bg-red-50 dark:border-red-500/40 dark:text-red-400 dark:hover:bg-red-950/40"
                                    aria-label="Remove this attachment row"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>

                        <div class="mt-2 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                id="add-attachment-row"
                                class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/20 dark:text-gray-200 dark:hover:bg-white/5"
                            >
                                <svg
                                    aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="h-4 w-4"
                                >
                                    <path d="M12 5v14" />
                                    <path d="M5 12h14" />
                                </svg>
                                Add more attachment
                            </button>
                            <p id="resolve-attachment-count" class="text-xs text-gray-500 dark:text-gray-400" aria-hidden="true"></p>
                        </div>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Allowed: jpg, jpeg, png, gif, pdf, doc, docx, mp4, avi, mov, webm — 20 MB each.</p>
                        @foreach ($errors->getMessages() as $field => $fieldErrors)
                            @if (in_array($field, ['attachments'], true) || str_starts_with($field, 'attachments.'))
                                @foreach ($fieldErrors as $message)
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @endforeach
                            @endif
                        @endforeach
                    </div>
                    <button type="submit" class="rounded-md bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-600 dark:bg-green-600 dark:hover:bg-green-500">Close complaint</button>
                </form>
            </section>

            <script>
                (function () {
                    var rows = document.getElementById('resolve-attachment-rows');
                    var addBtn = document.getElementById('add-attachment-row');
                    var countLabel = document.getElementById('resolve-attachment-count');
                    if (!rows || !addBtn || !countLabel) {
                        return;
                    }

                    var maxRows = 5;

                    function removeButtonsVisible() {
                        return rows.querySelectorAll('[data-attachment-row]').length > 1;
                    }

                    function refresh() {
                        var rowCount = rows.querySelectorAll('[data-attachment-row]').length;

                        rows.querySelectorAll('[data-remove-row]').forEach(function (button) {
                            button.hidden = !removeButtonsVisible();
                        });

                        countLabel.textContent = rowCount + ' of ' + maxRows;
                        addBtn.hidden = rowCount >= maxRows;
                    }

                    addBtn.addEventListener('click', function () {
                        var rowCount = rows.querySelectorAll('[data-attachment-row]').length;
                        if (rowCount >= maxRows) {
                            return;
                        }

                        var next = rows.querySelector('[data-attachment-row]').cloneNode(true);
                        next.querySelector('input[type=file]').value = '';
                        rows.appendChild(next);
                        refresh();
                        next.querySelector('input[type=file]').focus();
                    });

                    rows.addEventListener('click', function (event) {
                        var button = event.target.closest('[data-remove-row]');
                        if (!button) {
                            return;
                        }

                        var rowCount = rows.querySelectorAll('[data-attachment-row]').length;
                        if (rowCount <= 1) {
                            return;
                        }

                        button.closest('[data-attachment-row]').remove();
                        refresh();
                    });

                    refresh();
                })();
            </script>
        @endif
    </div>
</x-layouts.portal>
