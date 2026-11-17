
const data = window.bookingData;

const serviceSelect = document.querySelector('#service');
const employeeSelect = document.querySelector('#employee');
const calendar = document.querySelector('#calendar');

const prevButton = document.querySelector('#prev-day');
const nextButton = document.querySelector('#next-day');
const bookButton = document.querySelector('#book-button');
const message = document.querySelector('#booking-message');

let serviceId = '';
let employeeId = '';

let days = [];
let centerIndex = 0;

let selectedDate = '';
let selectedTime = '';

let requestNumber = 0;
let isSaving = false;

const serviceMap = new Map(
    data.services.map(service => [String(service.id), service])
);

const employeeMap = new Map(
    data.employees.map(employee => [String(employee.id), employee])
);

const allowed = new Set(
    data.assignments.map(item => `${item.service_id}:${item.employee_id}`)
);

function matches(service, employee) {
    return allowed.has(`${service}:${employee}`);
}

function fillSelect(select, items, selected, placeholder, getLabel) {

    select.replaceChildren(new Option(placeholder, ''));

    for (const item of items) {
        select.add(new Option(getLabel(item), String(item.id)));
    }

    select.value = selected;

}

function renderSelects() {

    const availableServices = data.services.filter(service =>
        !employeeId || matches(service.id, employeeId)
    );

    const availableEmployees = data.employees.filter(employee =>
        !serviceId || matches(serviceId, employee.id)
    );

    fillSelect(
        serviceSelect,
        availableServices,
        serviceId,
        'Wybierz usługę',
        service => `${service.category} — ${service.name}`
    );

    fillSelect(
        employeeSelect,
        availableEmployees,
        employeeId,
        'Wybierz barbera',
        employee => `${employee.name} ${employee.surname}`
    );

}

function formatDate(date) {

    return new Intl.DateTimeFormat('pl-PL', {
        weekday: 'short',
        day: 'numeric',
        month: 'short'
    }).format(new Date(`${date}T12:00:00`));

}

function setMessage(text, type = '') {

    message.textContent = text;
    message.className = `booking-message ${type}`;

}

function updateSummary() {

    const service = serviceMap.get(serviceId);
    const employee = employeeMap.get(employeeId);

    document.querySelector('#summary-service').textContent =
        service?.name ?? 'Nie wybrano';

    document.querySelector('#summary-employee').textContent =
        employee
            ? `${employee.name} ${employee.surname}`
            : 'Nie wybrano';

    document.querySelector('#summary-date').textContent =
        selectedDate && selectedTime
            ? `${formatDate(selectedDate)}, ${selectedTime}`
            : 'Nie wybrano';

    document.querySelector('#summary-duration').textContent =
        service ? `${service.duration} min` : '—';

    document.querySelector('#summary-price').textContent =
        service
            ? `${Number(service.price).toFixed(2).replace('.', ',')} zł`
            : '—';

    bookButton.disabled =
        !serviceId ||
        !employeeId ||
        !selectedDate ||
        !selectedTime ||
        isSaving;

    bookButton.textContent = isSaving
        ? 'Zapisywanie...'
        : 'Rezerwuj';

}

function renderCalendar() {

    calendar.replaceChildren();

    prevButton.disabled = !days.length || centerIndex === 0;
    nextButton.disabled = !days.length || centerIndex === days.length - 1;

    if (!days.length) {

        const info = document.createElement('p');

        info.className = 'booking-empty';
        info.textContent = 'Wybierz usługę i barbera, aby zobaczyć terminarz.';

        calendar.append(info);

        return;

    }

    // Wyświetlamy poprzedni, aktualny i następny dzień

    for (const index of [
        centerIndex - 1,
        centerIndex,
        centerIndex + 1
    ]) {

        const day = days[index];

        const column = document.createElement('div');
        column.className = 'booking-day-column';

        if (!day) {

            column.classList.add('booking-day-placeholder');
            calendar.append(column);

            continue;

        }

        column.classList.add(`day-${day.status}`);

        if (index === centerIndex) {
            column.classList.add('day-current');
        }

        const dateButton = document.createElement('button');

        dateButton.type = 'button';
        dateButton.className = 'booking-day-heading';
        dateButton.textContent = formatDate(day.date);

        dateButton.setAttribute(
            'aria-label',
            `Wybierz dzień ${formatDate(day.date)}`
        );

        dateButton.addEventListener('click', () => {

            centerIndex = index;
            renderCalendar();

        });

        column.append(dateButton);

        const hours = document.createElement('div');
        hours.className = 'booking-hours';

        if (!day.slots.length) {

            const info = document.createElement('p');

            info.className = 'booking-no-hours';

            info.textContent = day.status === 'closed'
                ? 'Nie pracuje'
                : 'Brak terminów';

            hours.append(info);

        } else {

            for (const time of day.slots) {

                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'booking-hour';
                button.textContent = time;

                if (
                    day.date === selectedDate &&
                    time === selectedTime
                ) {

                    button.classList.add('hour-selected');
                    button.setAttribute('aria-pressed', 'true');

                }

                button.addEventListener('click', () => {

                    selectedDate = day.date;
                    selectedTime = time;
                    centerIndex = index;

                    setMessage('');
                    renderCalendar();
                    updateSummary();

                });

                hours.append(button);

            }

        }

        column.append(hours);
        calendar.append(column);

    }

}

async function loadCalendar() {

    const currentRequest = ++requestNumber;

    days = [];
    centerIndex = 0;

    selectedDate = '';
    selectedTime = '';

    updateSummary();

    if (!serviceId || !employeeId) {

        renderCalendar();
        return;

    }

    calendar.replaceChildren();

    const loading = document.createElement('p');

    loading.className = 'booking-empty';
    loading.textContent = 'Wczytywanie terminów...';

    calendar.append(loading);

    prevButton.disabled = true;
    nextButton.disabled = true;

    try {

        const query = new URLSearchParams({
            api: 'slots',
            service_id: serviceId,
            employee_id: employeeId
        });

        const response = await fetch(
            `services.php?${query}`,
            { cache: 'no-store' }
        );

        const result = await response.json();

        if (currentRequest !== requestNumber) {
            return;
        }

        if (!response.ok) {
            throw new Error(result.error || 'Błąd pobierania terminów.');
        }

        days = result.days;

        renderCalendar();

    } catch (error) {

        if (currentRequest !== requestNumber) {
            return;
        }

        renderCalendar();
        setMessage(error.message, 'message-error');

    }

}

serviceSelect.addEventListener('change', () => {

    serviceId = serviceSelect.value;

    if (
        serviceId &&
        employeeId &&
        !matches(serviceId, employeeId)
    ) {
        employeeId = '';
    }

    renderSelects();
    setMessage('');
    loadCalendar();

});

employeeSelect.addEventListener('change', () => {

    employeeId = employeeSelect.value;

    if (
        serviceId &&
        employeeId &&
        !matches(serviceId, employeeId)
    ) {
        serviceId = '';
    }

    renderSelects();
    setMessage('');
    loadCalendar();

});

prevButton.addEventListener('click', () => {

    if (centerIndex > 0) {

        centerIndex--;
        renderCalendar();

    }

});

nextButton.addEventListener('click', () => {

    if (centerIndex < days.length - 1) {

        centerIndex++;
        renderCalendar();

    }

});

bookButton.addEventListener('click', async () => {

    if (
        !serviceId ||
        !employeeId ||
        !selectedDate ||
        !selectedTime ||
        isSaving
    ) {
        return;
    }

    isSaving = true;

    updateSummary();
    setMessage('');

    const form = new URLSearchParams({
        api: 'book',
        csrf_token: data.csrf,
        service_id: serviceId,
        employee_id: employeeId,
        date: selectedDate,
        time: selectedTime
    });

    try {

        const response = await fetch('services.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: form
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(
                result.error || 'Nie udało się zarezerwować wizyty.'
            );
        }

        await loadCalendar();

        setMessage(result.success, 'message-success');

    } catch (error) {

        setMessage(error.message, 'message-error');

        await loadCalendar();

    } finally {

        isSaving = false;
        updateSummary();

    }

});

renderSelects();
renderCalendar();
updateSummary();
