<x-layouts.portal title="Lodge Complaint - HT-CGRF">
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Lodge a Complaint</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Submit your grievance with details and supporting documents.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                Back to Dashboard
            </a>
        </div>

        <form action="{{ route('complaints.register') }}" method="POST" enctype="multipart/form-data" class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            @csrf

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label for="consumer_number" class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100">Consumer Number <span class="text-red-500">*</span></label>
                    <input type="text" name="consumer_number" id="consumer_number" value="{{ old('consumer_number', $consumer->consumer_number) }}" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-gray-100">
                    @error('consumer_number')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="category" class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100">Complaint Category <span class="text-red-500">*</span></label>
                    <select name="category" id="category" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">Select Category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->value }}" {{ old('category') == $category->value ? 'selected' : '' }}>{{ \Illuminate\Support\Str::headline($category->value) }}</option>
                        @endforeach
                    </select>
                    @error('category')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="subject" class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100">Subject <span class="text-red-500">*</span></label>
                <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-gray-100" placeholder="Enter complaint subject">
                @error('subject')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100">Complaint Details (Body) <span class="text-red-500">*</span></label>
                <textarea name="description" id="description" rows="6" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800 dark:text-gray-100" placeholder="Describe your grievance in detail">{{ old('description', old('body')) }}</textarea>
                @error('body')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @error('description')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-900 dark:text-gray-100">Attachments (Optional)</label>
                <div id="attachments-wrapper" class="space-y-2">
                    <div class="flex items-center gap-2">
                        <input type="file" name="attachments[]" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.mp4,.avi,.mov,.webm" class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-blue-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-blue-800 dark:text-gray-400 dark:file:bg-blue-700 dark:hover:file:bg-blue-600">
                    </div>
                </div>
                <button type="button" id="add-attachment" class="mt-2 rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-white/20 dark:text-gray-300 dark:hover:bg-white/5">Add more attachment</button>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Allowed: JPG, PNG, GIF, PDF, DOC, DOCX, MP4, AVI, MOV, WEBM (up to 5 files, 20 MB each).</p>
                @error('attachments')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @foreach ($errors->getMessages() as $field => $messages)
                    @if (str_starts_with($field, 'attachments.'))
                        @foreach ($messages as $message)
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($consumer->region)
                <input type="hidden" name="region" value="{{ $consumer->region }}">
            @endif

            <div class="flex items-center gap-4">
                <button type="submit" class="rounded-md bg-blue-900 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600">Lodge Complaint</button>
                <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        const addBtn = document.getElementById('add-attachment');
        const wrapper = document.getElementById('attachments-wrapper');
        if (addBtn && wrapper) {
            addBtn.addEventListener('click', function() {
                const fileCount = wrapper.querySelectorAll('input[type="file"]').length;
                if (fileCount >= 5) {
                    addBtn.disabled = true;
                    return;
                }

                const div = document.createElement('div');
                div.className = 'flex items-center gap-2';
                const input = document.createElement('input');
                input.type = 'file';
                input.name = 'attachments[]';
                input.accept = '.jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.mp4,.avi,.mov,.webm';
                input.className = 'block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-blue-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-blue-800 dark:text-gray-400 dark:file:bg-blue-700 dark:hover:file:bg-blue-600';
                div.appendChild(input);
                wrapper.appendChild(div);

                if (fileCount + 1 >= 5) {
                    addBtn.disabled = true;
                    addBtn.classList.add('cursor-not-allowed', 'opacity-50');
                }
            });
        }
    </script>
</x-layouts.portal>
