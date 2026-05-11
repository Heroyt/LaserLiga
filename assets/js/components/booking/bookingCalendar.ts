import { BookingStep, CollapseGroup, CollapsibleStep, errorAlert, EventListener } from "./bookingStep";
import { CalendarInterface, calendarObjects } from "../../pages/booking/calendar";
import { Collapse } from "bootstrap";
import { sprintf } from "../../functions";
import { validateInput } from "../../pages/booking/validators";
import { SubTypeInfo, TypeInfo } from "./bookingTypeSelect";
import { getSubtypeDatetime } from "../../api/endpoints/booking";
import { memo } from "../../api/client";

export type BookingCalendarEvents = {
    "next-step": void;
    "edit": void;
    'change' : void;
}

const memoizedGetSubtypeDatetime = memo(getSubtypeDatetime);

export interface BookingCalendarStep extends BookingStep<BookingCalendarEvents> {
    readonly calendar: CalendarInterface;
    readonly datetimeDescription: HTMLDivElement;

    setType(type : TypeInfo) : void;
    setSubtype(subtype : SubTypeInfo) : void;
    isCalendarFilled(): boolean;
    refreshTimePlayers(): void;
}

export abstract class BaseBookingCalendarStep extends EventListener implements BookingStep<BookingCalendarEvents> {
    hasError: boolean = false;
    public readonly datetimeDescription: HTMLDivElement;

    protected errorsWrapper: HTMLDivElement;
    protected playersSingleToggleWrapper: HTMLDivElement;
    protected playersSingleToggleInput: HTMLInputElement;
    protected playersWrapper: HTMLElement;
    protected playersSingleWrapper: HTMLDivElement;
    protected readonly playersSingleInput: HTMLInputElement;
    protected playerLockWrapper: HTMLDivElement;
    protected playerLockInput: HTMLInputElement;
    protected limit: number = 0;
    protected openable: boolean = true;
    protected openableMin: number = 0;
    protected openableDescription: string = "";
    protected singleSlotCountInput: boolean = false;
    protected minSlotCount: number = 0;
    protected maxSlotCount: number = 0;
    protected slotPreset: string = "1";

    protected constructor(
        public readonly element: HTMLElement,
        public readonly calendar: CalendarInterface
    ) {
        super();

        this.errorsWrapper = this.element.querySelector(".errors");
        this.datetimeDescription = this.element.querySelector<HTMLDivElement>(".description")!;
        this.playersWrapper = this.element.querySelector(".players");
        this.playersSingleToggleWrapper = this.element.querySelector(".players-single-toggle");
        this.playersSingleToggleInput = this.playersSingleToggleWrapper.querySelector("input");
        this.playersSingleWrapper = this.element.querySelector(".players-single");
        this.playersSingleInput = this.playersSingleWrapper.querySelector("input");
        this.playerLockWrapper = this.element.querySelector(".player-unlock-wrapper");
        this.playerLockInput = this.playerLockWrapper.querySelector("input");
    }

    setType(type : TypeInfo) : void {
        this.calendar.setType(type.id);
        this.limit = type.limit;
        this.openable = type.openable;
        this.openableMin = type.openableMin;
        this.openableDescription = type.openableDescription;
        this.slotPreset = "1";

        this.calendar.setPreset(this.slotPreset);
        this.calendar.reload();

        this.toggleOpenable();
    }

    setSubtype(subtype : SubTypeInfo) : void {
        if (subtype.slotPreset) {
            this.slotPreset = subtype.slotPreset;
        }

        this.calendar.setSubType(subtype.id);
        this.calendar.setPreset(this.slotPreset);
        this.calendar.reload();

        this.singleSlotCountInput = subtype.singleInput;
        this.minSlotCount = subtype.min;
        this.maxSlotCount = subtype.max;

        this.playersSingleToggleWrapper.style.display = this.singleSlotCountInput ? "none" : "block";
        if (this.singleSlotCountInput) {
            this.playersSingleToggleInput.checked = false;
        }

        this.playersSingleInput.min = this.minSlotCount.toString();
        this.playersSingleInput.value = this.minSlotCount.toString();
        if (this.maxSlotCount > 0) {
            this.playersSingleInput.max = this.maxSlotCount.toString();
        } else {
            this.playersSingleInput.removeAttribute("max");
        }
        this.playersSingleInput.valueAsNumber = this.minSlotCount > 0 ? this.minSlotCount : this.limit;

        this.playersSingleInput.classList.remove("is-valid", "is-invalid");
    }

    initEventListeners(): void {
        this.element.addEventListener("calendarReload", () => {
            this.refreshTimePlayers();
            this.clearStepError();
        });
        this.element.addEventListener("calendarTimeChange", () => {
            this.refreshTimePlayers();
        });

        // Player count input validation
        this.playersSingleInput.addEventListener("input", () => {
            this.playersSingleInput.classList.remove("is-invalid");
            this.clearStepError();

            // Copy value to all time inputs
            this.playersWrapper.querySelectorAll("input").forEach(input => {
                input.value = this.playersSingleInput.value;
            });

            this.maybeLockOpenable();
        });

        this.playersSingleInput.addEventListener("change", () => {
            if (this.playersSingleInput.valueAsNumber < this.minSlotCount) {
                this.playersSingleInput.valueAsNumber = this.minSlotCount;
            } else if (this.maxSlotCount > 0 && this.playersSingleInput.valueAsNumber > this.maxSlotCount) {
                this.playersSingleInput.valueAsNumber = this.maxSlotCount;
            }
            validateInput(this.playersSingleInput);
            this.dispatchEvent('change', undefined);
        });

        this.playersSingleToggleInput.addEventListener("change", () => {
            this.refreshTimePlayers();
            this.maybeLockOpenable();
            this.dispatchEvent('change', undefined);
        });
        this.refreshTimePlayers();
    }

    addError(key: string, error: string): boolean {
        if (key === "date" || key === "time") {
            this.errorsWrapper.appendChild(errorAlert(error));
            this.triggerStepError();
            return true;
        }
        if (key === "playerCount") {
            const errorMessage: HTMLDivElement = this.playersSingleWrapper.querySelector(".invalid-feedback");
            errorMessage.innerText = error;
            this.playersSingleInput.classList.add("is-invalid");
            this.triggerStepError();
            return true;
        }
        if (key.match(/players-\d{2}:\d{2}/)) {
            const playersWrapper = document.getElementById(key) as HTMLInputElement;
            const playersInput = playersWrapper.querySelector("input");
            if (playersInput) {
                playersInput.classList.add("is-invalid");
            }
            const playersFeedback = playersWrapper.querySelector(".invalid-feedback") as HTMLDivElement | undefined;
            if (playersFeedback) {
                playersFeedback.innerHTML = "";
                console.log(playersFeedback, error);
                playersFeedback.innerHTML = error;
            }
            this.triggerStepError();
            return true;
        }
        return false;
    }

    triggerStepError(): void {
        this.hasError = true;
        this.element.classList.add("is-invalid");
    }

    clearStepError(): void {
        this.hasError = false;
        this.element.classList.remove("is-invalid");
    }

    validate(): boolean {
        return false;
    }

    refreshTimePlayers() {
        this.toggleOpenable();
        if (this.singleSlotCountInput || !this.playersSingleToggleInput.checked) {
            this.showSinglePlayerCountInput();
        } else {
            this.hideSinglePlayerCountInput();

            // Update time players
            calendarObjects.forEach(calendar => {
                calendar.getSelectedTimesInfo().forEach(({ time, slots }) => {
                    this.addTimePlayers(time, slots);
                });
            });
            // Remove old inputs
            document.querySelectorAll(".time-players").forEach(dom => {
                if (dom.classList.contains("found")) {
                    dom.classList.remove("found");
                    return;
                }
                dom.remove();
            });
            // Sort inputs
            let players: HTMLElement[] = Array.prototype.slice.call(document.querySelectorAll(".time-players"), 0);
            players.sort((a: HTMLElement, b: HTMLElement) => {
                const aTime = parseInt(a.dataset.time);
                const bTime = parseInt(b.dataset.time);
                return aTime - bTime;
            });
            players.forEach(player => {
                this.playersWrapper.appendChild(player);
            });
        }
    }

    isCalendarFilled() {
        if (!this.calendar.getSelectedDate()) {
            return false;
        }
        return this.calendar.getSelectedTimes().length > 0;
    }

    /**
     * Adds the "player count" input for each new time selected
     * @param time Selected time string (value from the calendar)
     * @param limit Player limit
     * @private
     */
    protected addTimePlayers(time: string, limit: number) {
        let timeDom = document.getElementById("players-" + time);
        if (timeDom) {
            timeDom.classList.add("found");
            return;
        }
        const id = "players-" + time + "-input";
        timeDom = document.createElement("div");
        timeDom.id = "players-" + time;
        timeDom.classList.add("time-players", "form-group", "found");
        timeDom.dataset.time = time.replace(":", "");
        timeDom.setAttribute("data-time", timeDom.dataset.time);
        if (limit > 1) {
            timeDom.innerHTML = `<label for="${id}" class="form-label d-block">${sprintf(playersRangeLabel, time)}:</label>` +
                `<input required type="number" class="form-control" min="1" max="${limit}" id="${id}" name="players[${time}]" value="${limit}">` +
                `<div class="invalid-feedback"></div>`;
            const input = timeDom.querySelector("input");
            input.addEventListener("change", () => {
                validateInput(input);
            });
        } else {
            timeDom.innerHTML = `<div class="form-label">${sprintf(playersRangeLabel, time)}: <span class="value">${limit}</span></div>` +
                `<input type="hidden" id="${id}" name="players[${time}]" value="${limit}">`;
        }

        this.playersWrapper.appendChild(timeDom);

        if (!this.openable) {
            return;
        }

        // Maybe lock the openable checkbox
        const input = timeDom.querySelector("input");
        input.addEventListener("change", () => {
            this.maybeLockOpenable();
        });

    }

    protected maybeLockOpenable() {
        let max: number = 0;

        if (this.singleSlotCountInput || !this.playersSingleToggleInput.checked) {
            max = this.playersSingleInput.valueAsNumber;
            console.log("Single", max);
        } else {
            this.playersWrapper.querySelectorAll("input").forEach(currInput => {
                console.log(currInput, currInput.valueAsNumber, currInput.value);
                max = Math.max(max, currInput.valueAsNumber);
            });
        }

        console.log(max, this.openableMin);
        if (max >= this.openableMin) {
            this.playerLockInput.disabled = false;
        } else {
            this.playerLockInput.disabled = true;
            this.playerLockInput.checked = true;
        }
    }

    /**
     * Hide or show the "additional players can join" checkbox
     * @private
     */
    protected toggleOpenable(): void {
        this.playerLockWrapper.style.display = !this.openable || this.singleSlotCountInput ? "none" : "block";
        (this.playerLockWrapper.querySelector("#unlocked-description") as HTMLDivElement).innerText = this.openableMin > 0 ? this.openableDescription : "";
    }

    protected showSinglePlayerCountInput(): void {
        this.playersSingleWrapper.style.display = "block";
        this.playersWrapper.style.display = "none";
    }

    protected hideSinglePlayerCountInput(): void {
        this.playersSingleWrapper.style.display = "none";
        this.playersWrapper.style.display = "block";
    }
}

export class BookingCalendarStepPublic extends BaseBookingCalendarStep implements BookingCalendarStep, CollapsibleStep {
    result: CollapseGroup;
    select: CollapseGroup;
    private description : HTMLDivElement;

    constructor(
        element: HTMLElement,
        calendar: CalendarInterface
    ) {
        super(element, calendar);

        const resultElement = this.element.querySelector<HTMLDivElement>(".step-result");
        this.result = {
            element: resultElement,
            collapse: Collapse.getOrCreateInstance(resultElement, { toggle: false })
        };

        const selectElement = this.element.querySelector<HTMLDivElement>(".step-select");
        this.select = {
            element: selectElement,
            collapse: Collapse.getOrCreateInstance(selectElement, { toggle: false })
        };

        this.description = this.element.querySelector<HTMLDivElement>(".description");
    }

    initEventListeners() {
        super.initEventListeners();

        const nextStepBtn = this.select.element.querySelector(".next-step");
        nextStepBtn.addEventListener("click", () => {
            this.dispatchEvent("next-step", undefined);
        });

        this.result.element.querySelector(".edit").addEventListener("click", () => {
            this.dispatchEvent("edit", undefined);
        });

        this.element.addEventListener("calendarDateChange", () => {
            this.updateCalendarInfo();
        });
        this.element.addEventListener("calendarTimeChange", () => {
            if (this.isCalendarFilled()) {
                nextStepBtn.parentElement.classList.remove("d-none");
            } else {
                nextStepBtn.parentElement.classList.add("d-none");
            }
            this.updateCalendarInfo();
        });
    }

    validate(): boolean {
        let valid = true;

        if (!this.isCalendarFilled()) {
            this.addError("time", requiredTime);
            valid = false;
        }

        if (this.singleSlotCountInput && (!this.playersSingleInput.value || this.playersSingleInput.valueAsNumber < 1 || this.playersSingleInput.valueAsNumber < this.minSlotCount)) {
            this.addError("playerCount", missingPlayerCount);
            valid = false;
        }

        if (valid) {
            this.clearStepError();
        }

        return valid;
    }

    hide(): void {
        this.select.collapse.hide();
        this.result.collapse.show();
    }

    show(): void {
        this.select.collapse.show();
        this.result.collapse.hide();
    }

    setSubtype(subtype: SubTypeInfo) {
        super.setSubtype(subtype);
        this.fetchSubtype(subtype.id);
    }

    private updateCalendarInfo() {
        const selectInfo: HTMLElement = this.result.element.querySelector(".selected");
        if (!selectInfo) {
            return;
        }
        const date = this.calendar.getSelectedDateFormatted();
        const times = this.calendar.getSelectedTimes();
        console.log(date, times);
        if (date && times.length > 0) {
            selectInfo.innerText = date + ": " + times.join(", ");
        } else {
            selectInfo.innerText = "";
        }
    }

    private fetchSubtype(id : number) : void {
        memoizedGetSubtypeDatetime(id).then(response => {
                this.description.innerHTML = response;
            });
    }
}

export class BookingCalendarStepAdmin extends BaseBookingCalendarStep implements BookingCalendarStep {
    constructor(
        element: HTMLElement,
        calendar: CalendarInterface
    ) {
        super(element, calendar);
    }

    isCalendarFilled() {
        if (!this.calendar.getSelectedDate()) {
            return false;
        }
        return this.calendar.getSelectedTimes().length > 0;
    }

    protected maybeLockOpenable() {
        // Don't lock in admin mode
    }
}