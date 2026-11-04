document.addEventListener('DOMContentLoaded', function () {

    const storageKey = 'users_scroll_position';

    const savedPosition = sessionStorage.getItem(storageKey);

    if (savedPosition !== null) {
        sessionStorage.removeItem(storageKey);

        try {
            const position = JSON.parse(savedPosition);
            const row = document.getElementById('user-' + position.id);

            if (row) {
                requestAnimationFrame(function () {
                    const currentTop = row.getBoundingClientRect().top;

                    window.scrollBy({
                        top: currentTop - position.top,
                        behavior: 'instant'
                    });
                });
            }
        } catch (error) {
            console.error('Nie udało się przywrócić pozycji listy.', error);
        }
    }

    const actionForms = document.querySelectorAll(
        '.actions form'
    );

    actionForms.forEach(function (form) {

        form.addEventListener('submit', function (event) {

            if (event.defaultPrevented) {
                return;
            }

            const row = form.closest('tr');

            if (!row) {
                return;
            }

            const id = row.id.replace('user-', '');

            sessionStorage.setItem(
                storageKey,
                JSON.stringify({
                    id: id,
                    top: row.getBoundingClientRect().top
                })
            );
        });

    });

});