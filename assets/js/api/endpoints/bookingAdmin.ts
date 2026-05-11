import { fetchDelete, fetchGet, fetchPost, SuccessResponse } from "../client";
import { Booking, SlotStatus } from "../../pages/booking/interfaces";
import { prefixPathWithLang } from "../../functions";

export type CalendarResponse = {
    date: string,
    slots: SlotStatus[],
    bookings: Booking[],
    note: string|null,
};

export async function getCalendar(arenaId: number, typeId: number, date: string | null = null): Promise<CalendarResponse> {
    return fetchGet(prefixPathWithLang(`/admin/arenas/${arenaId}/booking/calendar/${typeId}`), { date });
}

export async function deleteBooking(arenaId: number, bookingId: number, notify: boolean = false, reason: string | null = null): Promise<SuccessResponse> {
    return fetchDelete(prefixPathWithLang(`/admin/arenas/${arenaId}/booking/${bookingId}`), { notify, reason });
}

export async function deleteBookingSlot(arenaId: number, bookingId: number, slot: string, notify: boolean = false, reason: string | null = null): Promise<SuccessResponse> {
    return fetchDelete(prefixPathWithLang(`/admin/arenas/${arenaId}/booking/${bookingId}/${slot.replace(':', '-')}`), { notify, reason });
}

export async function editNote(arenaId : number, typeId: number, date: string, note: string | null): Promise<SuccessResponse> {
    return fetchPost(`/admin/arenas/${arenaId}/booking/note/${typeId}`, { date, note });
}