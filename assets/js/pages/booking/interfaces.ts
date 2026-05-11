export interface SubTypeData {
    id: number,
    type: number,
    name: string,
    description: string,
    limitOverride: boolean,
    singlePlayerInput: boolean,
    slotMin: number | null,
    slotMax: number | null,
    mergeSlots: number | null,
    slotPreset: string | null,
}

export interface TypeData {
    id: number,
    icon: string,
    name: string,
    slotLength: number,
    slotLimit: number,
    subtypes: { [index: number]: SubTypeData }
}
export interface CalendarConfiguration {
    year: string,
    month: string,
    day: string,
    selectedDate: string,
    type: number | TypeData,
    subType: number | SubTypeData,
}

export interface TimeValue {
    time: string,
    slots: number
}

export type TimeStatus = 'AVAILABLE' | 'FILLED' | 'ON_CALL' | 'PARTIALLY_FILLED' | 'CLOSED';

export interface SlotStatus {
    datetime: string,
    time: string,
    status: TimeStatus,
    availableSpots: number,
}

export interface BookingTypeResponse {
    id: number,
    icon: string,
    name: string,
    slotLength: number,
    slotLimit: number,
    openable: boolean,
    openableMin: number,
}

export interface BookingSubTypeResponse {
    id: number,
    icon: string,
    name: string,
    description: string,
    datetimeDescription: string,
    infoDescription: string,
}

export interface BookingUser {
    id: number,
    email: string,
    firstName: string,
    lastName: string,
    phone: string,
    user: string|null,
}

export type BookingStatus = 'active' | 'complete';

export interface BookingSlot {
    time: string,
    span: number,
    playerCount: number,
    summary: string,
    description: string,
}

export interface BookingField {
    key: string,
    value: string|number|string[]|boolean,
    label: string|null,
    valueLabel: string|string[]|null,
    type: 'text'|'bool'|'select'|'multi'|'number',
}

export interface Booking {
    id: number,
    arena: number,
    type: BookingTypeResponse,
    subtype: null | BookingSubTypeResponse,
    users: BookingUser[],
    status: BookingStatus,
    date: string,
    slots: BookingSlot[],
    locked: boolean,
    note: string|null,
    privateNote: string|null,
    fields: null | BookingField[],
}