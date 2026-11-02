<x-layouts.portal title="Create Role">
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Create Role Mapping</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Assign a user to a role and map to hierarchy.</p>
            </div>
            <a href="{{ route('admin.roles.index') }}" class="rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                Roles
            </a>
        </div>

        <form action="{{ route('admin.roles.store') }}" method="POST" class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            @csrf

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label for="user_name" class="mb-1 block text-sm font-medium">User Name <span class="text-red-500">*</span></label>
                    <input type="text" name="user_name" id="user_name" value="{{ old('user_name') }}" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800" placeholder="Enter user name">
                    @error('user_name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="display_name" class="mb-1 block text-sm font-medium">Name</label>
                    <input type="text" name="display_name" id="display_name" value="{{ old('display_name') }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800" placeholder="Enter name">
                    @error('display_name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="designation" class="mb-1 block text-sm font-medium">Designation</label>
                    <input type="text" name="designation" id="designation" value="{{ old('designation') }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800" placeholder="Enter designation">
                    @error('designation')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="mobile" class="mb-1 block text-sm font-medium">Mobile</label>
                    <input type="text" name="mobile" id="mobile" value="{{ old('mobile') }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800" placeholder="Enter mobile number">
                    @error('mobile')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div id="login-credentials" class="hidden contents">
                    <div>
                        <label for="login_username" class="mb-1 block text-sm font-medium">Login Username <span class="text-red-500">*</span></label>
                        <input type="text" name="login_username" id="login_username" value="{{ old('login_username') }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800" placeholder="Enter login username">
                        @error('login_username')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium">Password <span class="text-red-500">*</span></label>
                        <input type="password" name="password" id="password" value="{{ old('password') }}" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800" placeholder="Enter password">
                        @error('password')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <input type="hidden" name="user_id" id="user_id" value="">

                <input type="hidden" name="role_name" id="role_name" value="">

                <div>
                    <label for="role" class="mb-1 block text-sm font-medium">Role Type</label>
                    <select name="role" id="role" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-white/20 dark:bg-gray-800">
                        <option value="">Select Role Type</option>
                        @foreach ($roleTypes as $roleType)
                            <option value="{{ $roleType->code }}" {{ old('role') == $roleType->code ? 'selected' : '' }}>{{ $roleType->name }}</option>
                        @endforeach
                        <option value="site_admin" {{ old('role') == 'site_admin' ? 'selected' : '' }}>Site Admin</option>
                    </select>
                    @error('role')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <x-admin.role-hierarchy-fields :discoms="$discoms" :selected="[
                    'discom_id' => old('discom_id'),
                    'circle_id' => old('circle_id'),
                    'division_id' => old('division_id'),
                    'subdivision_id' => old('subdivision_id'),
                    'section_id' => old('section_id'),
                ]" />
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:bg-blue-700 dark:hover:bg-blue-600">Save</button>
                <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        const roleSelect = document.getElementById('role');
        const loginCreds = document.getElementById('login-credentials');
        const loginUser = document.getElementById('login_username');
        const loginPass = document.getElementById('password');

        function toggleCreds() {
            const role = roleSelect.value;
            if (role === 'relationship_manager' || role === 'competent_authority' || role === 'site_admin') {
                loginCreds.classList.remove('hidden');
                loginUser.setAttribute('required', 'required');
                loginPass.setAttribute('required', 'required');
            } else {
                loginCreds.classList.add('hidden');
                loginUser.removeAttribute('required');
                loginPass.removeAttribute('required');
            }
        }

        roleSelect.addEventListener('change', toggleCreds);
        toggleCreds();
    </script>
</x-layouts.portal>
