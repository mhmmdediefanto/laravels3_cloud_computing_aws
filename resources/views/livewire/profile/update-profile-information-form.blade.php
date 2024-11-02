<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    public string $name = '';
    public string $email = '';
    public $photo; // Properti untuk foto profil

    use WithFileUploads;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'], // Validasi foto, maksimal 1MB
        ]);

        if ($this->photo) {
            // Hapus foto lama jika ada
            if ($user->profile_photo_path) {
                Storage::disk('s3')->delete($user->profile_photo_path);
            }

            // Simpan foto baru ke S3 dan dapatkan path-nya
            $path = $this->photo->store('profile-photos', 's3');
            $user->profile_photo_path = $path;
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="flex flex-col md:flex-row">
    <div class="w-full md:w-1/2">
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Profile Information') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __("Update your account's profile information and email address.") }}
            </p>
        </header>

        <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
            <div>
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full"
                    required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full"
                    required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !auth()->user()->hasVerifiedEmail())
                    <div>
                        <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                            {{ __('Your email address is unverified.') }}
                            <button wire:click.prevent="sendVerification"
                                class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                                {{ __('Click here to re-send the verification email.') }}
                            </button>
                        </p>
                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Input Foto -->
            <div>
                <x-input-label for="photo" :value="__('Profile Photo')" />
                <input wire:model="photo" id="photo" name="photo" type="file" class="mt-1 block w-full" />
                <x-input-error class="mt-2" :messages="$errors->get('photo')" />
                @if ($this->photo)
                    <img src="{{ $this->photo->temporaryUrl() }}" class="mt-2 w-20 h-20 rounded-full">
                @elseif (Auth::user()->profile_photo_path)
                    <img src="{{ Storage::disk('s3')->url(Auth::user()->profile_photo_path) }}" alt=""
                        class="mt-2 w-20 h-20 rounded-full">
                @endif
            </div>
            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </div>

    <!-- Gambar Profil -->
    @if (Auth::user()->profile_photo_path)
        <div class="w-full md:w-1/2 flex items-center justify-end">
            <img src="{{ Storage::disk('s3')->url(Auth::user()->profile_photo_path) }}" alt=""
                class=" w-[230px] ">
        </div>
    @endif
</section>
