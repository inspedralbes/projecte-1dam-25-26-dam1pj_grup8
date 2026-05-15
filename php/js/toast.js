// Solo mostrar el mensaje cuando se registra correctamente
document.addEventListener('DOMContentLoaded', () => {
    const toastElements = document.querySelectorAll('.js-toast-notification');

    toastElements.forEach((toastElement) => {
        // return si bootstrap no está disponible
        if (typeof bootstrap === 'undefined' || typeof bootstrap.Toast === 'undefined') {
            return;
        }

        const toast = bootstrap.Toast.getOrCreateInstance(toastElement);
        toast.show();
    });
});