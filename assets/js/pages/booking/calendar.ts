// noinspection LoopStatementThatDoesntLoopJS

import { initTooltips } from "../../functions";
import { CalendarConfiguration, TimeValue } from "./interfaces";
import { getCalendar } from "../../api/endpoints/booking";

export let calendarObjects: CalendarInterface[] = [];

type PresetValue = {
    length: number;
    forced: boolean;
}

export default function initCalendar() {
    const calendarElements: NodeListOf<HTMLDivElement> = document.querySelectorAll(".calendar");
    for (const calendar of calendarElements) {
        // Skip already initialized calendars
        if (calendar.dataset.initialized === "true") {
            continue;
        }

        calendarObjects.push(new BookingCalendar(calendar));
    }
}

export type CalendarEvents = "calendarDateChange" | "calendarTimeChange" | "calendarReload";

export interface CalendarInterface {
    reload(): void;

    getSelectedDate(): string | null;

    getSelectedDateFormatted(): string | null;

    getSelectedTimesInfo(): TimeValue[];

    getSelectedTimes(): string[];

    setPreset(preset: string): void;

    setType(type: number): void;

    setSubType(subtype: number): void;
}

export class CompositeBookingCalendar implements CalendarInterface {
    getSelectedDate(): string | null {
        for (const calendar of calendarObjects) {
            return calendar.getSelectedDate();
        }
        return null;
    }

    getSelectedDateFormatted(): string | null {
        for (const calendar of calendarObjects) {
            return calendar.getSelectedDateFormatted();
        }
        return null;
    }

    getSelectedTimes(): string[] {
        for (const calendar of calendarObjects) {
            return calendar.getSelectedTimes();
        }
        return [];
    }

    getSelectedTimesInfo(): TimeValue[] {
        for (const calendar of calendarObjects) {
            return calendar.getSelectedTimesInfo();
        }
        return [];
    }

    reload(): void {
        for (const calendar of calendarObjects) {
            calendar.reload();
        }
    }

    setPreset(preset: string): void {
        for (const calendar of calendarObjects) {
            calendar.setPreset(preset);
        }
    }

    setType(type: number): void {
        for (const calendar of calendarObjects) {
            calendar.setType(type);
        }
    }

    setSubType(subtype: number): void {
        for (const calendar of calendarObjects) {
            calendar.setSubType(subtype);
        }
    }
}

export class BookingCalendar implements CalendarInterface {
    public obj: HTMLDivElement;
    public arenaSlug: string;
    public configuration: CalendarConfiguration;
    private preset: PresetValue[] = [{ length: 1, forced: true, }];
    private forcedInputs : Set<HTMLInputElement>[] = [];
    private timeCount: number = 0;

    /**
     * Calendar constructor
     *
     * @param {HTMLElement|Element} obj
     */
    constructor(obj: HTMLDivElement) {
        this.init(obj);
    }

    /**
     * Initializes the calendar HTML element
     *
     * Initializes all event listeners, set-up config, etc..
     *
     * @param {HTMLElement|Element} obj
     */
    init(obj: HTMLDivElement) {
        this.obj = obj;
        initTooltips(this.obj);

        this.arenaSlug = this.obj.dataset.arena;

        let config = this.obj.dataset.configuration.replaceAll("\\\"", "\"");
        this.configuration = JSON.parse(config);

        this.obj.dataset.initialized = "true";

        (this.obj.querySelectorAll(".changeDate") as NodeListOf<HTMLElement>).forEach(btn => {
            btn.addEventListener("click", () => {
                if (btn.dataset.year) {
                    this.configuration.year = btn.dataset.year;
                }
                if (btn.dataset.month) {
                    this.configuration.month = btn.dataset.month;
                }
                if (btn.dataset.day) {
                    this.configuration.day = btn.dataset.day;
                }
                this.reload();
                this.timeCount = 0;
                this.emitEvent("calendarDateChange");
                this.emitEvent("calendarTimeChange");
            });
        });

        (this.obj.querySelectorAll(".dateSelect") as NodeListOf<HTMLInputElement>).forEach(input => {
            input.addEventListener("change", () => {
                const date = input.value;
                const [year, month, day] = date.split("-");
                this.configuration.year = year;
                this.configuration.month = month;
                this.configuration.day = day;
                this.configuration.selectedDate = date;
                this.reload();
                this.timeCount = 0;
                this.emitEvent("calendarDateChange");
                this.emitEvent("calendarTimeChange");
            });
        });

        (this.obj.querySelectorAll(".timeSelect") as NodeListOf<HTMLInputElement>).forEach(input => {
            input.addEventListener("change", () => {
                // If there are already selected forced inputs, times should be reset when selecting a new time.
                if (
                    this.timeCount > 0 // Not a first selection
                    && this.forcedInputs.length > 0  // There are some forced inputs selected
                    && input.checked // The user is selecting a new time (not unselecting)
                ) {
                    for (const forcedInputs of this.forcedInputs) {
                        for (const forcedInput of forcedInputs) {
                            forcedInput.checked = false;
                        }
                    }
                    this.forcedInputs = [];
                    this.timeCount = 0;
                }

                // First selected time
                if (this.timeCount === 0) {
                    // Apply preset
                    let slot = true;
                    let currentInput: HTMLInputElement | null = input;
                    let stop = false;
                    let forcedGroup : Set<HTMLInputElement>|null = null;

                    // Preset is a list of consecutive slots to select, alternating between selected and unselected.
                    // Optionally each step can be forced, meaning the slots must always be selected together.
                    for (const { length, forced } of this.preset) {
                        for (let i = 0; i < length; ++i) {
                            if (slot) {
                                if (parseInt(currentInput.dataset.available) > 0) {
                                    currentInput.checked = true;
                                }

                                if (forced) {
                                    if (!forcedGroup) {
                                        forcedGroup = new Set<HTMLInputElement>();
                                    }
                                    forcedGroup.add(currentInput);
                                }
                            }
                            currentInput = this.getNextSiblingInput(currentInput);
                            if (!currentInput) {
                                stop = true;
                                break;
                            }
                        }

                        if (forcedGroup) {
                            this.forcedInputs.push(forcedGroup);
                            forcedGroup = null;
                        }

                        if (stop) {
                            break;
                        }
                        slot = !slot;
                    }
                }
                else if (!input.checked) {
                    // Check if unchecked input is part of some forced group and uncheck all inputs in that group.
                    // Then remove all those groups from the list.
                    const groupsToRemove : number[] = [];
                    for (const key in this.forcedInputs) {
                        const group = this.forcedInputs[key];
                        if (group.has(input)) {
                            group.forEach(elem => elem.checked = false);
                            groupsToRemove.push(parseInt(key));
                        }
                    }
                    groupsToRemove.reverse().forEach(key => this.forcedInputs.splice(key, 1));
                }

                this.timeCount = this.getSelectedTimes().length;

                this.emitEvent("calendarTimeChange");
            });
        });
    }

    /**
     * Reloads the whole calendar using an AJAX call
     */
    reload(): void {
        this.obj.querySelector(".loading").classList.remove("d-none");
        getCalendar(this.arenaSlug, this.configuration)
            .then(response => {
                const wrapper = document.createElement("div");
                wrapper.innerHTML = response;
                const newCalendar: HTMLDivElement = wrapper.querySelector(".calendar");
                this.obj.parentNode.replaceChild(newCalendar, this.obj);
                this.init(newCalendar);
            })
            .catch(response => {
                console.error(response);
            })
            .finally(() => {
                this.timeCount = 0;
                this.obj.querySelector(".loading").classList.add("d-none");
                this.emitEvent("calendarReload");
            });
    }

    /**
     * Gets the selected date
     * @returns {string|null} Date string or null if no date is selected
     */
    getSelectedDate(): string | null {
        const selectedInput: HTMLInputElement = this.obj.querySelector(".dateSelect:checked");
        if (selectedInput) {
            return selectedInput.value;
        }
        return null;
    }

    /**
     * Gets the selected date
     * @returns {string|null} Date string or null if no date is selected
     */
    getSelectedDateFormatted(): string | null {
        const selectedInput: HTMLInputElement = this.obj.querySelector(".dateSelect:checked");
        if (selectedInput) {
            return selectedInput.dataset.formatted;
        }
        return null;
    }

    getSelectedTimesInfo(): TimeValue[] {
        let values: TimeValue[] = [];
        const selectedInputs: NodeListOf<HTMLInputElement> = this.obj.querySelectorAll(".timeSelect:checked");
        if (selectedInputs.length > 0) {
            selectedInputs.forEach(input => {
                values.push({
                    time: input.value,
                    slots: parseInt(input.dataset.available ?? "0")
                });
            });
            return values;
        }
        return [];
    }

    /**
     * Gets the selected time
     * @returns {string[]} - Time string or null if no time is selected
     */
    getSelectedTimes(): string[] {
        let values: string[] = [];
        const selectedInputs: NodeListOf<HTMLInputElement> = this.obj.querySelectorAll(".timeSelect:checked");
        if (selectedInputs.length > 0) {
            selectedInputs.forEach(input => {
                values.push(input.value);
            });
            return values;
        }
        return [];
    }

    setPreset(preset: string): void {
        const parts = preset.split("-");
        this.preset = [];
        parts.forEach(part => {
            this.preset.push({
                length: parseInt(part),
                forced: part.endsWith('!'),
            });
        });
        console.log({preset, parsed: this.preset});
    }

    setType(type: number): void {
        this.configuration.type = type;
    }

    setSubType(subtype: number | null): void {
        this.configuration.subType = subtype;
    }

    /**
     * Emit a DOM event, that other scripts can listen to
     *
     * @param {string} eventName
     * @param {boolean} bubbles
     */
    private emitEvent(eventName: CalendarEvents, bubbles: boolean = true) {
        const e = new CustomEvent(eventName, { bubbles });
        this.obj.dispatchEvent(e);
    }

    private getNextSiblingInput(input: HTMLInputElement): HTMLInputElement | null {
        let elem: HTMLElement = input;
        do {
            elem = elem.nextElementSibling as HTMLElement;
            if (elem instanceof HTMLInputElement) {
                return elem;
            }
        } while (elem);
        return null;
    }
}

export class DatelessCalendar implements CalendarInterface {
    public obj: HTMLDivElement;
    public configuration: CalendarConfiguration;
    private preset: number[] = [1];
    private timeCount: number = 0;

    /**
     * Calendar constructor
     *
     * @param {HTMLElement|Element} obj
     */
    constructor(obj: HTMLDivElement) {
        this.init(obj);
    }

    /**
     * Initializes the calendar HTML element
     *
     * Initializes all event listeners, set-up config, etc..
     *
     * @param {HTMLElement|Element} obj
     */
    init(obj: HTMLDivElement) {
        this.obj = obj;
        initTooltips(this.obj);

        let config = this.obj.dataset.configuration.replaceAll("\\\"", "\"");
        this.configuration = JSON.parse(config);

        this.obj.dataset.initialized = "true";

        (this.obj.querySelectorAll(".timeSelect") as NodeListOf<HTMLInputElement>).forEach(input => {
            input.addEventListener("change", () => {
                // First selected time
                if (this.timeCount === 0) {
                    // Apply preset
                    let slot = true;
                    let currentInput: HTMLInputElement | null = input;
                    let stop = false;
                    for (const count of this.preset) {
                        for (let i = 0; i < count; ++i) {
                            if (slot) {
                                if (parseInt(currentInput.dataset.available) > 0) {
                                    currentInput.checked = true;
                                }
                            }
                            currentInput = this.getNextSiblingInput(currentInput);
                            if (!currentInput) {
                                stop = true;
                                break;
                            }
                        }
                        if (stop) {
                            break;
                        }
                        slot = !slot;
                    }
                }

                this.timeCount = this.getSelectedTimes().length;

                this.emitEvent("calendarTimeChange");
            });
        });
    }

    /**
     * Reloads the whole calendar using an AJAX call
     */
    reload(): void {
        return;
    }

    /**
     * Gets the selected date
     * @returns {string|null} Date string or null if no date is selected
     */
    getSelectedDate(): string | null {
        return this.configuration.selectedDate;
    }

    /**
     * Gets the selected date
     * @returns {string|null} Date string or null if no date is selected
     */
    getSelectedDateFormatted(): string | null {
        return this.obj.dataset.date ?? null;
    }

    /**
     * Gets the selected time
     * @returns {{time:string,slots:number}[]} - Time string or null if no time is selected
     */
    getSelectedTimesInfo(): TimeValue[] {
        let values: TimeValue[] = [];
        const selectedInputs: NodeListOf<HTMLInputElement> = this.obj.querySelectorAll(".timeSelect:checked");
        if (selectedInputs.length > 0) {
            selectedInputs.forEach(input => {
                values.push({
                    time: input.value,
                    slots: parseInt(input.dataset.available ?? "0")
                });
            });
            return values;
        }
        return [];
    }

    /**
     * Gets the selected time
     * @returns {string[]} - Time string or null if no time is selected
     */
    getSelectedTimes(): string[] {
        let values: string[] = [];
        const selectedInputs: NodeListOf<HTMLInputElement> = this.obj.querySelectorAll(".timeSelect:checked");
        if (selectedInputs.length > 0) {
            selectedInputs.forEach(input => {
                values.push(input.value);
            });
            return values;
        }
        return [];
    }

    setPreset(preset: string): void {
        const parts = preset.split("-");
        this.preset = [];
        parts.forEach(part => {
            this.preset.push(parseInt(part));
        });
    }

    setType(type: number): void {
        this.configuration.type = type;
    }

    setSubType(subtype: number): void {
        this.configuration.subType = subtype;
    }

    /**
     * Emit a DOM event, that other scripts can listen to
     *
     * @param {string} eventName
     * @param {boolean} bubbles
     */
    private emitEvent(eventName: CalendarEvents, bubbles: boolean = true) {
        const e = new CustomEvent(eventName, { bubbles });
        this.obj.dispatchEvent(e);
    }

    private getNextSiblingInput(input: HTMLInputElement): HTMLInputElement | null {
        let elem: HTMLElement = input;
        do {
            elem = elem.nextElementSibling as HTMLElement;
            if (elem instanceof HTMLInputElement) {
                return elem;
            }
        } while (elem);
        return null;
    }
}

export const compositeCalendars = new CompositeBookingCalendar();
