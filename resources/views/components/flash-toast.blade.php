@props([
    'message' => null,
    'type' => 'success',
])

@php
    $statusMessages = [
        'profile-updated' => 'Profile updated successfully.',
        'password-updated' => 'Password updated successfully.',
        'verification-link-sent' => 'A new verification link has been sent to your email address.',
    ];

    $rawMessage = is_string($message) ? trim($message) : null;
    $resolvedMessage = $rawMessage !== null && array_key_exists($rawMessage, $statusMessages) ? $statusMessages[$rawMessage] : $rawMessage;
    $shouldRender = $resolvedMessage !== null && $resolvedMessage !== '';

    $styleByType = [
        'success' => [
            'container' => 'border-emerald-600 bg-emerald-600 text-white',
            'icon' => 'text-white',
        ],
        'info' => [
            'container' => 'border-blue-600 bg-blue-600 text-white',
            'icon' => 'text-white',
        ],
    ];

    $style = $styleByType[$type] ?? $styleByType['success'];
@endphp

@if ($shouldRender)
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 3800)"
        x-show="show"
        x-transition:enter="transform ease-out duration-300"
        x-transition:enter-start="translate-y-2 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transform ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="translate-y-2 opacity-0"
        class="pointer-events-auto fixed bottom-16 left-1/2 z-[70] w-full max-w-sm -translate-x-1/2 px-3"
    >
        <div class="rounded-lg border shadow-lg {{ $style['container'] }}">
            <div class="flex items-start gap-3 px-4 py-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 {{ $style['icon'] }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.172 7.707 8.879a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <p class="flex-1 text-sm font-medium">
                    {{ $resolvedMessage }}
                </p>
                <button type="button" @click="show = false" class="rounded p-1 text-white/80 hover:bg-white/20 hover:text-white" aria-label="Dismiss">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
@endif
