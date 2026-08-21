let deferredPrompt = null;
let serviceWorkerRegistration = null;
let waitingWorker = null;
let reloadingForUpdate = false;

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIos = () => /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.userAgent.includes('Macintosh') && navigator.maxTouchPoints > 1);

const banner = () => document.getElementById('pwa-install-banner');

const hideBanner = () => banner()?.classList.add('hidden');

const revealBanner = () => {
    const element = banner();

    if (! element || isStandalone()) {
        hideBanner();

        return;
    }

    const iosInstructions = element.querySelector('[data-pwa-ios]');
    const installButton = element.querySelector('[data-pwa-install]');
    const ios = isIos();

    iosInstructions?.classList.toggle('hidden', ! ios);
    installButton?.classList.toggle('hidden', ios || ! deferredPrompt);
    element.classList.toggle('hidden', ! ios && ! deferredPrompt);
};

const bindBanner = () => {
    const element = banner();

    if (! element || element.dataset.bound === 'true') {
        revealBanner();

        return;
    }

    element.dataset.bound = 'true';
    element.querySelector('[data-pwa-dismiss]')?.addEventListener('click', hideBanner);
    element.querySelector('[data-pwa-install]')?.addEventListener('click', async () => {
        if (! deferredPrompt) {
            return;
        }

        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        hideBanner();
    });

    revealBanner();
};

const showUpdateBanner = (worker) => {
    waitingWorker = worker;
    const element = document.getElementById('pwa-update-banner');

    if (! element) {
        return;
    }

    if (element.dataset.bound !== 'true') {
        element.dataset.bound = 'true';
        element.querySelector('[data-pwa-update]')?.addEventListener('click', () => {
            waitingWorker?.postMessage({ type: 'SKIP_WAITING' });
        });
    }

    element.classList.remove('hidden');
};

const checkForUpdates = () => {
    serviceWorkerRegistration?.update().catch(() => {
        // The next page load retries the update check.
    });
};

const registerServiceWorker = async () => {
    serviceWorkerRegistration = await navigator.serviceWorker.register('/serviceworker.js', {
        scope: '/',
        updateViaCache: 'none',
    });

    if (serviceWorkerRegistration.waiting && navigator.serviceWorker.controller) {
        showUpdateBanner(serviceWorkerRegistration.waiting);
    }

    serviceWorkerRegistration.addEventListener('updatefound', () => {
        const installingWorker = serviceWorkerRegistration.installing;

        installingWorker?.addEventListener('statechange', () => {
            if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                showUpdateBanner(installingWorker);
            }
        });
    });

    checkForUpdates();
};

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    revealBanner();
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    hideBanner();
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', registerServiceWorker);
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloadingForUpdate) {
            return;
        }

        reloadingForUpdate = true;
        window.location.reload();
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            checkForUpdates();
        }
    });
}

document.addEventListener('DOMContentLoaded', bindBanner);
document.addEventListener('livewire:navigated', () => {
    bindBanner();
    checkForUpdates();
});
