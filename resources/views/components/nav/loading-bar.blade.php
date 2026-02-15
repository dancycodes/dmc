{{--
    Global Navigation Loading Bar
    ----------------------------
    Thin progress bar at the top of the page that appears during
    Gale SPA navigation transitions. Uses $gale.loading global state.
    BR-133: A global loading indicator must appear during Gale navigation transitions.
--}}
<div
    x-data="{ visible: false }"
    x-init="
        $watch('$gale.loading', (loading) => {
            visible = loading;
        })
    "
    x-show="visible"
    x-transition:enter="transition-opacity duration-150"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed top-0 left-0 right-0 z-[100] h-0.5 pointer-events-none"
    x-cloak
>
    <div class="h-full bg-primary animate-loading-bar"></div>
</div>

<style>
    @keyframes loading-bar {
        0% { width: 0; }
        20% { width: 30%; }
        50% { width: 60%; }
        80% { width: 85%; }
        100% { width: 95%; }
    }
    .animate-loading-bar {
        animation: loading-bar 2s ease-in-out infinite;
    }
</style>
