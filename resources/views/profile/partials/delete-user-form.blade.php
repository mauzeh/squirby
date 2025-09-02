<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Delete Account
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.
        </p>
    </header>

    <button type="button" class="button danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        Delete Account
    </button>

    <div x-data="{ show: false }" x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 items-center justify-center" style="display: none;">
        <div x-show="show" class="fixed inset-0 transform transition-all" x-on:click="show = false">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <div x-show="show" class="mb-6 bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:max-w-2xl" x-on:click.away="show = false" x-on:keydown.escape="show = false">
            <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
                @csrf
                @method('delete')

                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete your account?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.
                </p>

                <div class="mt-6">
                    <label for="password" class="sr-only">Password</label>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        class="form-control mt-1 block w-3/4"
                        placeholder="Password"
                    />

                    @error('password', 'userDeletion')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="button" class="button secondary" x-on:click="show = false">
                        Cancel
                    </button>

                    <button type="submit" class="button danger ms-3">
                        Delete Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>