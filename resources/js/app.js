import './pwa-install';

const loading = () => document.getElementById('global-loading');
const showLoading = () => loading()?.classList.replace('hidden', 'grid');
const hideLoading = () => loading()?.classList.replace('grid', 'hidden');

document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({ respond, succeed, fail }) => {
        showLoading();
        respond(hideLoading);
        succeed(hideLoading);
        fail(hideLoading);
    });
});

document.addEventListener('livewire:navigating', showLoading);
document.addEventListener('livewire:navigated', hideLoading);
window.addEventListener('pageshow', hideLoading);
