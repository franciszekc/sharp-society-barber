document.addEventListener('DOMContentLoaded', function () {

    const storageKey = 'services_scroll_position';

    const savedPosition = sessionStorage.getItem(storageKey);

    if (savedPosition !== null) {
        sessionStorage.removeItem(storageKey);

        requestAnimationFrame(function () {
            window.scrollTo({
                top: Number(savedPosition),
                behavior: 'instant'
            });
        });
    }

    const toggleForms = document.querySelectorAll(
        'form:has(input[name="action"][value="toggle"])'
    );

    toggleForms.forEach(function (form) {

        form.addEventListener('submit', function () {
            sessionStorage.setItem(
                storageKey,
                String(window.scrollY)
            );
        });

    });

});
