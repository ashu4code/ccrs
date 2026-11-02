<x-layouts.portal title="Edit Role">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-xl font-semibold">Edit Role Mapping</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Update role and hierarchy for {{ $user->name }}.</p>
        </div>

        <form action="{{ route('admin.roles.update', $user) }}" method="POST" class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label for="role_name" class="mb-1 block text-sm font-medium">User Name</label>
                    <input type="text" name="role_name" id="role_name" value="{{ old('role_name', $user->name) }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800">
                </div>
                <div>
                    <label for="display_name" class="mb-1 block text-sm font-medium">Name</label>
                    <input type="text" name="display_name" id="display_name" value="{{ old('display_name', $user->display_name) }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800">
                    @error('display_name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Email/Login</label>
                    <input type="text" value="{{ $user->email }}" disabled class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-gray-50 dark:border-white/20 dark:bg-gray-800">
                </div>

                <div>
                    <label for="designation" class="mb-1 block text-sm font-medium">Designation</label>
                    <input type="text" name="designation" id="designation" value="{{ old('designation', $user->designation) }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800">
                </div>

                <div>
                    <label for="mobile" class="mb-1 block text-sm font-medium">Mobile</label>
                    <input type="text" name="mobile" id="mobile" value="{{ old('mobile', $user->mobile) }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800">
                </div>

                <div>
                    <label for="role" class="mb-1 block text-sm font-medium">Role Type</label>
                    <select name="role" id="role" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800">
                        <option value="" @selected(old('role', $user->role?->value) === null || old('role', $user->role?->value) === '')>None</option>
                        @foreach ($roleTypes as $roleType)
                            <option value="{{ $roleType->code }}" @selected(old('role', $user->role?->value) == $roleType->code)>{{ $roleType->name }}</option>
                        @endforeach
                        <option value="site_admin" {{ old('role', $user->role?->value) == 'site_admin' ? 'selected' : '' }}>Site Admin</option>
                    </select>
                </div>

                <div id="login-credentials" class="hidden">
                    <label for="password" class="mb-1 block text-sm font-medium">New Password (leave blank to keep current)</label>
                    <input type="password" name="password" id="password" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800" placeholder="Enter new password">
                </div>

                <x-admin.role-hierarchy-fields :discoms="$discoms" :selected="[
                    'discom_id' => old('discom_id', $user->discom_id),
                    'circle_id' => old('circle_id', $user->circle_id),
                    'division_id' => old('division_id', $user->division_id),
                    'subdivision_id' => old('subdivision_id', $user->subdivision_id),
                    'section_id' => old('section_id', $user->section_id),
                ]" />
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600">Update</button>
                <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        const roleSelect = document.getElementById('role');
        const loginCreds = document.getElementById('login-credentials');
        function toggleCreds() {
            const r = roleSelect ? roleSelect.value : '';
            if (r === 'relationship_manager' || r === 'competent_authority' || r === 'site_admin') {
                if (loginCreds) loginCreds.classList.remove('hidden');
            } else {
                if (loginCreds) {
                    loginCreds.classList.add('hidden');
                    const pass = document.getElementById('password');
                    if (pass) pass.value = '';
                }
            }
        }
        if (roleSelect) {
            roleSelect.addEventListener('change', toggleCreds);
            toggleCreds();
        }
    </script>
</x-layouts.portal>
