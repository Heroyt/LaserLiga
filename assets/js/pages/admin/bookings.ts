import {
    CalendarResponse,
    deleteBooking,
    deleteBookingSlot,
    editNote,
    getCalendar
} from "../../api/endpoints/bookingAdmin";
import { triggerNotification, triggerNotificationError } from "../../components/notifications";
import { startLoading, stopLoading } from "../../loaders";
import { Booking, BookingSlot } from "../booking/interfaces";
import { initTooltips, prefixPathWithLang } from "../../functions";
import { Modal } from "bootstrap";
import DOMPurify from "dompurify";

declare global {
    // @ts-ignore
    const arenaId: number;
    const canView: boolean;
    const canEdit: boolean;
    const messages: {
        edit: string,
        delete: string,
        confirmDelete: string,
        playerCount: string,
        isLocked: string,
    }
}

const loader = document.createElement("div");
loader.classList.add("text-center", "my-3");
loader.innerHTML = `<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>`;

const bottomOffset = 300;

const maxGapBeforeReset = 14;
const maxDaysToLoad = 14; // Prevent loading too many days

export default async function initAdminBookings() {
    const bookingTypeSelect = document.getElementById("filter-type") as HTMLSelectElement;
    const bookingDateInput = document.getElementById("filter-date") as HTMLInputElement;
    const calendarContainer = document.getElementById("booking-calendar") as HTMLDivElement;
    const showAllBookings = document.getElementById('show-all-details') as HTMLInputElement;
    if (!bookingTypeSelect || !bookingDateInput || !calendarContainer) {
        console.error("Missing required elements", { bookingTypeSelect, bookingDateInput, calendarContainer });
        return;
    }

    let selectedBooking : Booking|null = null;
    let selectedBookingSlot : BookingSlot|null = null;
    let selectedDay : string|null = null;
    let selectedDayNote : HTMLSpanElement|null = null;

    const deleteModalDiv = document.getElementById("booking-delete-modal") as HTMLDivElement;
    const deleteModal = Modal.getOrCreateInstance(deleteModalDiv);

    const deleteSlotsModalDiv = document.getElementById("booking-delete-slots-modal") as HTMLDivElement;
    const deleteSlotsModal = Modal.getOrCreateInstance(deleteSlotsModalDiv);

    const dayNoteModalDiv = document.getElementById("booking-note-modal") as HTMLDivElement;
    const dayNoteTextarea = dayNoteModalDiv.querySelector<HTMLTextAreaElement>("textarea#booking-day-note");
    const dayNoteDate = dayNoteModalDiv.querySelector<HTMLSpanElement>(".date");
    const dayNoteModal = Modal.getOrCreateInstance(dayNoteModalDiv);

    if (!bookingTypeSelect.value) {
        triggerNotification({
            type: "warning",
            content: "Invalid booking type"
        });
        return;
    }

    const loadedDays: Set<string> = new Set();

    bookingDateInput.addEventListener("change", async () => {
        if (!bookingDateInput.value) {
            return;
        }
        const date = formatDate(bookingDateInput.value);
        console.log("Changed date", {value: bookingDateInput.value, date});
        const day = await loadBookingDay(date);

        // Find gaps in loaded days and load them
        const gaps = findGapsInLoadedDays();
        // Check if any gap is too big
        let tooBig = false;
        for (const gap of gaps) {
            if (gap.length > maxGapBeforeReset) {
                tooBig = true;
                break;
            }
        }

        if (tooBig) {
            reset();
            // Re-add the current day
            if (day) {
                loadedDays.add(date);
                calendarContainer.appendChild(day);
            }
        } else {
            // Load gaps
            for (const gap of gaps) {
                await loadInGap(gap);
            }
        }

        if (day) {
            day.scrollIntoView({ behavior: "smooth", block: "start" });
        }

        // Update the URL parameter `date` without reloading the page
        const url = new URL(window.location.href);
        url.searchParams.set("date", date);
        window.history.replaceState({}, '', url.toString());
    });

    bookingTypeSelect.addEventListener("change", async () => {
        reset();
        await loadBookingDay(formatDate(bookingDateInput.value));

        // Update the URL parameter `type` without reloading the page
        const url = new URL(window.location.href);
        url.searchParams.set("type", bookingTypeSelect.value);
        window.history.replaceState({}, '', url.toString());
    });

    if (showAllBookings) {
        showAllBookings.addEventListener("change", () => {
            if (showAllBookings.checked) {
                calendarContainer.classList.add('uncollapse-all');
            }
            else {
                calendarContainer.classList.remove('uncollapse-all');
            }
        })
    }

    // Infinite scroll for calendar
    let loading = true;
    calendarContainer.appendChild(loader);

    await loadBookingDay(formatDate(bookingDateInput.value));

    loading = false;
    calendarContainer.removeChild(loader);

    let prevScroll = 0;
    calendarContainer.addEventListener("scroll", async () => {
        // Find date in view
        const daysInView = calendarContainer.querySelectorAll<HTMLDivElement>(".calendar-day");
        const calendarTop = calendarContainer.getBoundingClientRect().y;
        for (const day of daysInView) {
            const top = day.getBoundingClientRect().y - calendarTop;
            if (top > 0 && top < (calendarContainer.clientHeight / 2)) { // Element's top is in the top half of the container
                const dateString = day.dataset.date;
                // Format date to DD-MM-YYYY and set it to the input
                if (dateString) {
                    const date = new Date(dateString);
                    bookingDateInput.value = `${date.getDate().toString().padStart(2, "0")}.${(date.getMonth() + 1).toString().padStart(2, "0")}.${date.getFullYear()}`;
                }
                break;
            }
        }

        if (loading) {
            return;
        }
        const movingDown = calendarContainer.scrollTop > prevScroll;
        const movingUp = calendarContainer.scrollTop < prevScroll;
        prevScroll = calendarContainer.scrollTop;

        // Detect if we're near the bottom
        if (movingDown && calendarContainer.scrollTop + calendarContainer.clientHeight >= calendarContainer.scrollHeight - bottomOffset) {
            await loadNextDay();
            return;
        }

        // Detect if we're near the top
        if (movingUp && calendarContainer.scrollTop <= bottomOffset) {
            await loadPreviousDay();
            return;
        }
    });

    // Preemptively preload the previous day
    await loadPreviousDay();

    initDeleteModal();
    initDeleteSlotsModal();
    initDayNoteModal();

    function initDeleteModal() {
        deleteModalDiv.addEventListener("hide.bs.modal", () => {
            selectedBooking = null;
            selectedBookingSlot = null;
        });

        const notificationCheck = deleteModalDiv.querySelector<HTMLInputElement>("#booking-delete-notification");
        const deleteReason = deleteModalDiv.querySelector<HTMLTextAreaElement>("#booking-delete-reason");
        const confirmDeleteBtn = deleteModalDiv.querySelector<HTMLButtonElement>("button.delete");

        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener("click", async () => {
                if (!selectedBooking) {
                    triggerNotification({
                        type: "warning",
                        content: "No booking selected"
                    });
                    deleteModal.hide();
                    return;
                }
                const bookingId = selectedBooking.id;
                const bookingDate = selectedBooking.date.substring(0, 10); // Extract only the YYYY-MM-DD part
                startLoading();
                let reason = null;
                if (deleteReason && deleteReason.value.trim()) {
                    reason = deleteReason.value.trim();
                }
                let notify = false;
                if (notificationCheck && notificationCheck.checked) {
                    notify = true;
                }

                try {
                    await deleteBooking(arenaId, bookingId, notify, reason);
                } catch (e) {
                    await triggerNotificationError(e);
                    stopLoading(false);
                    return;
                }
                stopLoading(true);
                // Remove all bookings with this ID
                const allBookings = document.querySelectorAll<HTMLDivElement>(`.booking[data-id="${bookingId}"]`);
                for (const b of allBookings) {
                    b.remove();
                }
                triggerNotification({
                    type: "success",
                    content: messages.confirmDelete
                });
                deleteModal.hide();

                // Reload the booking day just to make sure
                await loadBookingDay(bookingDate);
            });
        }
    }

    function initDeleteSlotsModal() {
        const slotList = deleteSlotsModalDiv.querySelector<HTMLUListElement>("#booking-delete-slots-list");
        const slotTime = deleteSlotsModalDiv.querySelector<HTMLSpanElement>("#booking-delete-slots-time");

        deleteSlotsModalDiv.addEventListener("hide.bs.modal", () => {
            selectedBooking = null;
            selectedBookingSlot = null;
            slotList.innerHTML = "";
            slotTime.innerHTML = "";
        });
        deleteSlotsModalDiv.addEventListener("show.bs.modal", () => {
            if (!selectedBooking) {
                return;
            }

            for (const slot of selectedBooking.slots) {
                const li = document.createElement("li");
                if (selectedBookingSlot && slot.time === selectedBookingSlot.time) {
                    li.innerHTML = `<strong>${slot.time}</strong>`;
                }
                else {
                    li.innerHTML = slot.time;
                }
                slotList.appendChild(li);
            }

            if (selectedBookingSlot) {
                slotTime.innerText = selectedBookingSlot.time;
            }
        });

        const notificationCheck = deleteSlotsModalDiv.querySelector<HTMLInputElement>("#booking-delete-slots-notification");
        const deleteReason = deleteSlotsModalDiv.querySelector<HTMLTextAreaElement>("#booking-delete-slots-reason");
        const confirmDeleteBtn = deleteSlotsModalDiv.querySelector<HTMLButtonElement>("button.delete");
        const confirmDeleteSlotBtn = deleteSlotsModalDiv.querySelector<HTMLButtonElement>("button.delete-slot");

        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener("click", async () => {
                if (!selectedBooking) {
                    triggerNotification({
                        type: "warning",
                        content: "No booking selected"
                    });
                    deleteSlotsModal.hide();
                    return;
                }
                const bookingId = selectedBooking.id;
                const bookingDate = selectedBooking.date.substring(0, 10); // Extract only the YYYY-MM-DD part
                startLoading();
                let reason = null;
                if (deleteReason && deleteReason.value.trim()) {
                    reason = deleteReason.value.trim();
                }
                let notify = false;
                if (notificationCheck && notificationCheck.checked) {
                    notify = true;
                }

                try {
                    await deleteBooking(arenaId, bookingId, notify, reason);
                } catch (e) {
                    await triggerNotificationError(e);
                    stopLoading(false);
                    return;
                }
                stopLoading(true);
                // Remove all bookings with this ID
                const allBookings = document.querySelectorAll<HTMLDivElement>(`.booking[data-id="${bookingId}"]`);
                for (const b of allBookings) {
                    b.remove();
                }
                triggerNotification({
                    type: "success",
                    content: messages.confirmDelete
                });
                deleteSlotsModal.hide();

                // Reload the booking day just to make sure
                await loadBookingDay(bookingDate);
            });
        }
        if (confirmDeleteSlotBtn) {
            confirmDeleteSlotBtn.addEventListener("click", async () => {
                if (!selectedBooking || !selectedBookingSlot) {
                    triggerNotification({
                        type: "warning",
                        content: "No booking selected"
                    });
                    deleteSlotsModal.hide();
                    return;
                }
                const bookingId = selectedBooking.id;
                const slotTime = selectedBookingSlot.time;
                const bookingDate = selectedBooking.date.substring(0, 10); // Extract only the YYYY-MM-DD part
                startLoading();
                let reason = null;
                if (deleteReason && deleteReason.value.trim()) {
                    reason = deleteReason.value.trim();
                }
                let notify = false;
                if (notificationCheck && notificationCheck.checked) {
                    notify = true;
                }

                try {
                    await deleteBookingSlot(arenaId, bookingId, slotTime, notify, reason);
                } catch (e) {
                    await triggerNotificationError(e);
                    stopLoading(false);
                    return;
                }
                stopLoading(true);
                // Remove all bookings with this ID and time
                const allBookings = document.querySelectorAll<HTMLDivElement>(`.booking[data-id="${bookingId}"][data-slot="${slotTime}"]`);
                for (const b of allBookings) {
                    b.remove();
                }
                triggerNotification({
                    type: "success",
                    content: messages.confirmDelete
                });
                deleteSlotsModal.hide();

                // Reload the booking day just to make sure
                await loadBookingDay(bookingDate);
            });
        }
    }

    function initDayNoteModal() {
        dayNoteModalDiv.addEventListener("show.bs.modal", () => {
            if (!selectedDay || !selectedDayNote) {
                return;
            }

            dayNoteTextarea.value = selectedDayNote.innerHTML.replaceAll('<br>', '\n');
            dayNoteDate.innerText = selectedDay;
        });
        dayNoteModalDiv.addEventListener("hide.bs.modal", async () => {
            if (!selectedDay || !selectedDayNote) {
                return;
            }

            // Send note
            startLoading(true);

            selectedDayNote.innerHTML = DOMPurify.sanitize(dayNoteTextarea.value.replaceAll('\n', '<br>'));

            try {
                await editNote(arenaId, parseInt(bookingTypeSelect.value), selectedDay, dayNoteTextarea.value);
                stopLoading(true, true);
            } catch (e) {
                stopLoading(false,true);
                await triggerNotificationError(e);
                return;
            }


            selectedDay = null;
            selectedDayNote = null;
        });
    }

    /**
     * @param date Y-m-d formatted date
     * @param removeOldDays
     */
    async function loadBookingDay(date: string, removeOldDays = true) : Promise<HTMLDivElement|null> {
        try {
            startLoading(true);
            const response = await getCalendar(arenaId, parseInt(bookingTypeSelect.value), date);

            const day = createOrUpdateCalendarDay(date, response);

            const noteDiv = day.querySelector<HTMLSpanElement>(".note .text");
            if (noteDiv) {
                noteDiv.innerHTML = DOMPurify.sanitize(response.note ? response.note.replaceAll('\n', '<br>') : '');
            }

            for (const booking of response.bookings) {
                let fields = '';

                if (booking.fields) {
                    for (const field of booking.fields) {
                        let valueLabel = '';
                        if (Array.isArray(field.valueLabel)) {
                            valueLabel = field.valueLabel.join(", ");
                        } else if (field.type === 'bool') {
                            valueLabel = field.value ? '<i class="fa-solid fa-check text-success"></i>' : '<i class="fa-solid fa-xmark text-danger"></i>';
                        } else if (field.type === 'number') {
                            valueLabel = field.value.toLocaleString();
                        } else {
                            valueLabel = field.valueLabel ? field.valueLabel.toString() : '';
                        }

                        fields += `<div class="field"><strong>${field.label ?? field.key}:</strong> ${valueLabel || field.value}</div>`;
                    }
                }

                for (const slot of booking.slots) {
                    const bookingDiv = createOrGetBooking(day, booking, slot);

                    if (booking.locked) {
                        bookingDiv.classList.add("locked");
                    } else {
                        bookingDiv.classList.remove("locked");
                    }

                    if (booking.status === "complete") {
                        bookingDiv.classList.add("complete");
                    } else {
                        bookingDiv.classList.remove("complete");
                    }

                    const type = bookingDiv.querySelector<HTMLDivElement>('.type');
                    if (type) {
                        type.innerHTML = booking.subtype?.icon ? `<span class="me-3" title="${booking.subtype.name}" data-toggle="tooltip">${booking.subtype.icon}</span>` : '';
                        type.innerHTML += `<span class="playerCount fs-6"><strong>${messages.playerCount}:</strong> ${slot.playerCount}</span>`;
                    }

                    // Update booking details
                    const header = bookingDiv.querySelector<HTMLDivElement>(".info");
                    if (header) {
                        const firstUser = booking.users[0];
                        header.innerHTML = `
                            <div class="user">
                                <i class="fa-solid fa-user me-1"></i> <strong>${firstUser.firstName} ${firstUser.lastName}</strong><br>
                                <i class="fa-solid fa-envelope me-1"></i> <a href="mailto:${firstUser.email}">${firstUser.email}</a><br>
                                <i class="fa-solid fa-phone me-1"></i> <a href="tel:${firstUser.phone}">${firstUser.phone}</a><br>
                            </div>
                        `;
                    }
                    const details = bookingDiv.querySelector<HTMLDivElement>(".details");
                    if (details) {
                        details.innerHTML = `<div class="inner">`
                                +(booking.note.trim() ? `<div class="note"><i class="fa-solid fa-note-sticky me-1"></i> ${booking.note.replace("\n", '<br>')}</div>` : '')
                                +(booking.privateNote.trim() ? `<div class="private-note"><i class="fa-solid fa-eye-slash me-1"></i> ${booking.privateNote.replace("\n", '<br>')}</div>` : '')
                                +(fields ? `<div class="fields">${fields}</div>` : '')
                            +`</div>`;

                        // Check if the inner div overflows the details div
                        const innerDiv = details.querySelector<HTMLDivElement>(".inner");
                        if (innerDiv && innerDiv.scrollHeight > details.clientHeight) {
                            // Add a collapse indicator to the details div
                            const collapseIndicator = document.createElement("div");
                            collapseIndicator.classList.add("collapse-indicator");
                            collapseIndicator.innerHTML = `<i class="fa-solid fa-angle-down collapse-indicator-collapsed"></i><i class="fa-solid fa-angle-up collapse-indicator-not-collapsed"></i>`;
                            details.appendChild(collapseIndicator);
                        }
                    }

                    initTooltips(bookingDiv);
                }
            }

            stopLoading(true, true);
            loadedDays.add(date); // Remember that we've loaded this day
            if (removeOldDays) {
                maybeRemoveOldDays(date);
            }
            return day;
        } catch (err) {
            console.error(err);
            stopLoading(false, true);
            triggerNotification(err);
        }
        return null;
    }

    function createOrGetBooking(day: HTMLDivElement, booking: Booking, slot: BookingSlot): HTMLDivElement {
        let bookingDiv = day.querySelector<HTMLDivElement>(`.booking[data-id="${booking.id}"][data-slot="${slot.time}"]`);
        if (!bookingDiv) {
            bookingDiv = document.createElement("div");
            bookingDiv.classList.add("booking", 'collapsed');
            bookingDiv.dataset.id = booking.id.toString();
            bookingDiv.dataset.slot = slot.time;
            bookingDiv.dataset.status = booking.status;
            bookingDiv.innerHTML =
                `<div class="inner"><div class="header"><div class="type"></div>`
                +`<div class="actions">`
                +`<button class="btn btn-primary edit${!canEdit ? ' d-none' : ''}" data-toggle="tooltip" title="${messages.edit}"><i class="fa-solid fa-pencil"></i></button>`+
                `<button class="btn btn-danger delete${!canEdit ? ' d-none' : ''}" data-toggle="tooltip" title="${messages.delete}"><i class="fa-solid fa-trash"></i></button>`
                +`</div></div>`
                + `<div class="info"></div><div class="details"></div></div>`;

            const slotDiv = day.querySelector<HTMLDivElement>(`.slot[data-time="${slot.time}"]`);
            if (slotDiv) {
                slotDiv.querySelector('.bookings').appendChild(bookingDiv);
            }
            else {
                console.error("Could not find slot for booking", { booking, slot, day });
            }

            initBooking(bookingDiv, booking, slot);
        }
        return bookingDiv;
    }

    /**
     * @param date Y-m-d formatted date
     * @param response
     */
    function createOrUpdateCalendarDay(date: string, response: CalendarResponse): HTMLDivElement {
        const slots = response.slots;
        let day = calendarContainer.querySelector<HTMLDivElement>(`.calendar-day[data-date="${date}"]`);
        if (!day) {
            day = document.createElement("div");
            day.classList.add("calendar-day");
            day.dataset.date = date;
            day.dataset.day = (new Date(date)).toLocaleDateString();

            day.innerHTML = `<div class="day-header"><div class="date">${day.dataset.day}</div><div class="note"><span class="text"></span>${canEdit ? `<button class="btn btn-sm btn-link edit-note" type="button">` : ''}<i class="fa-solid fa-pencil"></i></button></div></div>`;

            let lastDay: HTMLDivElement | null = null;
            // Make sure to append the day to the container in the correct order
            for (const existingDay of calendarContainer.querySelectorAll<HTMLDivElement>(".calendar-day")) {
                const existingDate = existingDay.dataset.date;
                if (existingDate && existingDate > date) {
                    lastDay = existingDay;
                    break;
                }
            }

            if (lastDay) {
                calendarContainer.insertBefore(day, lastDay);
            } else {
                calendarContainer.appendChild(day);
            }

            const noteTextDiv = day.querySelector<HTMLSpanElement>(`.note .text`);
            const editNoteBtn = day.querySelector<HTMLButtonElement>('.edit-note');
            if (editNoteBtn) {
                editNoteBtn.addEventListener("click", () => {
                    selectedDay = date;
                    selectedDayNote = noteTextDiv;
                    dayNoteModal.show();
                });
            }
        }

        let gridRows = "auto [";
        // Add slots to the day
        for (const slot of slots) {
            const timeKey = "t-" + slot.time.replace(":", "-");
            gridRows += ` ${timeKey}-start ] auto [ ${timeKey}-end`;

            let slotDiv = day.querySelector<HTMLDivElement>(`.slot[data-time="${slot.time}"]`);
            if (!slotDiv) {
                slotDiv = document.createElement("div");
                slotDiv.classList.add("slot");
                slotDiv.dataset.time = slot.time;
                slotDiv.dataset.date = date;
                slotDiv.dataset.status = slot.status;
                day.appendChild(slotDiv);

                slotDiv.innerHTML = `<div class="time">${slot.status === 'ON_CALL' ? '<i class="fa-solid fa-phone"></i>' : ''}${slot.time}</div><div class="bookings"></div><button class="btn btn-success add"><i class="fa-solid fa-plus"></i></button>`;

                initSlot(slotDiv);
            }

            if (slot.status === 'ON_CALL') {
                slotDiv.classList.add("on-call");
            }
            else {
                slotDiv.classList.remove("on-call");
            }
            if (slot.status === 'CLOSED') {
                slotDiv.classList.add("closed");
            }
            else {
                slotDiv.classList.remove("closed");
            }
            if (slot.status === 'FILLED') {
                slotDiv.classList.add("filled");
            }
            else {
                slotDiv.classList.remove("filled");
            }
            if (slot.status === 'PARTIALLY_FILLED') {
                slotDiv.classList.add("partially-filled");
            }
            else {
                slotDiv.classList.remove("partially-filled");
            }

            slotDiv.dataset.time = slot.time;
            slotDiv.dataset.status = slot.status;
            const timeDiv = slotDiv.querySelector<HTMLDivElement>('.time');
            timeDiv.innerHTML = `${slot.status === 'ON_CALL' ? '<i class="fa-solid fa-phone"></i>' : ''}${slot.time}`;
            slotDiv.style.gridRow = timeKey;
        }

        gridRows += " ]";

        day.style.gridTemplateRows = gridRows;

        return day;
    }

    function reset() : void {
        loadedDays.clear();
        calendarContainer.innerHTML = "";
    }

    function initSlot(slot: HTMLDivElement) {
        const time = slot.dataset.time;
        const date = slot.dataset.date;
        const addBtn = slot.querySelector<HTMLButtonElement>("button.add");
        if (addBtn) {
            addBtn.addEventListener("click", () => {
                if (!time) {
                    triggerNotification({
                        type: "warning",
                        content: "Invalid time slot"
                    });
                    return;
                }

                const selectedType = bookingTypeSelect.value;

                const url = new URL(window.location.origin + window.location.pathname); // Current URL without query parameters
                url.pathname = prefixPathWithLang(`/admin/arenas/${arenaId}/booking/create/${selectedType}`);
                url.searchParams.set('datetime', `${date} ${time}`);

                window.location.href = url.toString();
            });
        }
    }

    function initBooking(bookingDiv: HTMLDivElement, booking: Booking, slot : BookingSlot) {
        initTooltips(bookingDiv);
        bookingDiv.addEventListener("mouseenter", () => {
            // Add hover effect to all bookings with the same ID
            const id = bookingDiv.dataset.id;
            if (!id) {
                return;
            }
            const allBookings = document.querySelectorAll<HTMLDivElement>(`.booking[data-id="${id}"]`);
            allBookings.forEach(b => b.classList.add("hover"));
        });
        bookingDiv.addEventListener("mouseleave", () => {
            // Remove hover effect to all bookings with the same ID
            const id = bookingDiv.dataset.id;
            if (!id) {
                return;
            }
            const allBookings = document.querySelectorAll<HTMLDivElement>(`.booking[data-id="${id}"]`);
            allBookings.forEach(b => b.classList.remove("hover"));
        });

        const editBtn = bookingDiv.querySelector<HTMLButtonElement>("button.edit");
        if (editBtn) {
            editBtn.addEventListener("click", () => {
                const id = bookingDiv.dataset.id;
                if (!id) {
                    triggerNotification({
                        type: "warning",
                        content: "Invalid booking ID"
                    });
                    return;
                }

                window.location.href = prefixPathWithLang(`/admin/arenas/${arenaId}/booking/${id}/edit`);
            });
        }

        const deleteBtn = bookingDiv.querySelector<HTMLButtonElement>("button.delete");
        if (deleteBtn) {
            deleteBtn.addEventListener("click", () => {
                const id = bookingDiv.dataset.id;
                if (!id) {
                    triggerNotification({
                        type: "warning",
                        content: "Invalid booking ID"
                    });
                    return;
                }

                selectedBooking = booking;
                selectedBookingSlot = slot;

                console.log(booking, slot);

                if (booking.slots.length > 1) {
                    deleteSlotsModal.show();
                }
                else {
                    deleteModal.show();
                }
            });
        }

        bookingDiv.addEventListener("click", () => {
            for (const expanded of calendarContainer.querySelectorAll<HTMLDivElement>('.booking:not(.collapsed)')) {
                if (expanded === bookingDiv) {
                    continue;
                }
                expanded.classList.add("collapsed");
            }
            bookingDiv.classList.toggle("collapsed");
        })
    }

    function findGapsInLoadedDays() : string[][] {
        const gaps : string[][] = [];

        // Sort all loaded days
        const days = Array.from(loadedDays).sort();

        // Find gaps
        for (let i = 1; i < days.length; i++) {
            const prevDay = Date.parse(days[i - 1]);
            const currentDay = Date.parse(days[i]);
            const diffTime = currentDay - prevDay;
            const diffDays = diffTime / (1000 * 60 * 60 * 24);

            if (diffDays > 1) {
                const gap : string[] = [];

                // Generate missing days
                for (let j = 1; j < diffDays; j++) {
                    const missingDate = new Date(prevDay);
                    missingDate.setDate(missingDate.getDate() + j);
                    const missingDateString = missingDate.toISOString().split("T")[0];
                    if (!loadedDays.has(missingDateString)) {
                        gap.push(missingDateString);
                    }
                }
                gaps.push(gap);
            }
        }

        return gaps;
    }

    async function loadInGap(days : string[]) : Promise<void> {
        const promises : Promise<HTMLDivElement|null>[] = [];

        // load gap
        for (const day of days) {
            promises.push(loadBookingDay(day, false)); // Load without removing old days
        }

        // Wait for all loading to finish
        await Promise.all(promises);
    }

    function getNextDateToLoad() : string {
        // Sort all loaded days
        const days = Array.from(loadedDays).sort();
        if (days.length === 0) {
            return formatDate(bookingDateInput.value);
        }
        const lastDay = days[days.length - 1];
        const nextDate = new Date(Date.parse(lastDay));
        // Increment by one day
        nextDate.setDate(nextDate.getDate() + 1);
        return nextDate.toISOString().split("T")[0];
    }

    function getPreviousDateToLoad() : string {
        // Sort all loaded days
        const days = Array.from(loadedDays).sort();
        if (days.length === 0) {
            return formatDate(bookingDateInput.value);
        }
        const firstDay = days[0];
        const nextDate = new Date(Date.parse(firstDay));
        // Decrement by one day
        nextDate.setDate(nextDate.getDate() - 1);
        return nextDate.toISOString().split("T")[0];
    }

    function maybeRemoveOldDays(currentDate : string) : void {
        if (loadedDays.size <= maxDaysToLoad) {
            return;
        }

        console.log("Reached a limit of loaded days, removing old days", { count: loadedDays.size });

        // Sort all loaded days
        do {
            const days = Array.from(loadedDays).sort();

            // Remember the current scroll position to keep it stable
            const scrollTop = calendarContainer.scrollTop;

            // Find the day furthest from the current date
            // Because the dates are sorted, it's either the first or the last
            const firstDay = days[0];
            const lastDay = days[days.length - 1];
            const current = Date.parse(currentDate);
            const firstDiff = Math.abs(Date.parse(firstDay) - current);
            const lastDiff = Math.abs(Date.parse(lastDay) - current);
            if (firstDiff > lastDiff) {
                // Remove first day
                const dayDiv = calendarContainer.querySelector<HTMLDivElement>(`.calendar-day[data-date="${firstDay}"]`);
                const dayHeight = dayDiv ? dayDiv.clientHeight : 0;
                console.log("Removing first day", { firstDay, dayDiv });
                if (dayDiv) {
                    dayDiv.remove();
                }
                loadedDays.delete(firstDay);

                // Day removed above, so restore scroll position
                calendarContainer.scrollTop = scrollTop - dayHeight;
            } else {
                // Remove last day
                const dayDiv = calendarContainer.querySelector<HTMLDivElement>(`.calendar-day[data-date="${lastDay}"]`);
                console.log("Removing last day", { lastDay, dayDiv });
                if (dayDiv) {
                    dayDiv.remove();
                }
                loadedDays.delete(lastDay);
            }
        } while (loadedDays.size > maxDaysToLoad);
    }

    async function loadPreviousDay() {
        loading = true;

        calendarContainer.prepend(loader);

        // Remember the current scroll position to keep it stable
        const scrollTop = calendarContainer.scrollTop;

        // Load previous date
        const prevDate = getPreviousDateToLoad();
        const prevDay = await loadBookingDay(prevDate);
        if (prevDay) {
            // Day added above, so restore scroll position
            const dayHeight = prevDay.clientHeight;
            calendarContainer.scrollTop = scrollTop + dayHeight;
        }

        calendarContainer.removeChild(loader);
        loading = false;
    }

    async function loadNextDay() {
        loading = true;

        calendarContainer.appendChild(loader);

        // Load next day
        const nextDate = getNextDateToLoad();
        console.log('Loading next date', {nextDate, loadedDays});
        await loadBookingDay(nextDate);

        calendarContainer.removeChild(loader);
        loading = false;
    }
}

function formatDate(date: string): string {
    // Try the DD.MM.YYYY format first
    if (date.match(/\d{1,2}.\s*.\d{1,2}.\s*\d{4}/)) {
        const parts = date.split(".");
        if (parts.length === 3) {
            const day = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10);
            const year = parseInt(parts[2], 10);
            if (!isNaN(day) && !isNaN(month) && !isNaN(year)) {
                return `${year}-${month.toString().padStart(2, "0")}-${day.toString().padStart(2, "0")}`;
            }
        }
        throw new Error(`${date} is not a valid date`);
    }

    // Try the default parser
    let parsedDate = Date.parse(date);
    if (!isNaN(parsedDate)) {
        const d = new Date(parsedDate);
        return d.toISOString().split("T")[0];
    }

    throw new Error(`${date} is not a valid date`);
}