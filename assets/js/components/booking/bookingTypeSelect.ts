import { BookingStep, CollapseGroup, CollapsibleStep, errorAlert, EventListener } from "./bookingStep";
import { Collapse } from "bootstrap";

export type TypeInfo = {
    id: number;
    limit: number;
    name: string;
    openable: boolean;
    openableMin: number;
    openableDescription: string;
    input: HTMLInputElement;
}

export type SubTypeInfo = {
    id: number;
    name: string;
    min: number | null;
    max: number | null;
    singleInput: boolean;
    slotPreset: string | null;
    input: HTMLInputElement;
}

export type BookingTypeEvents = {
    'type-change': TypeInfo|null;
    'subtype-change': SubTypeInfo|null;
    'next-step': void;
    'edit': void;
};

export interface BookingTypeStep extends BookingStep<BookingTypeEvents> {
    getSelectedType(): TypeInfo | null;
    getSelectedSubType(): SubTypeInfo | null;
}

export abstract class BaseBookingTypeStep extends EventListener implements BookingStep<BookingTypeEvents> {
    element: HTMLElement;
    hasError: boolean = false;
    protected errorsWrapper: HTMLDivElement;

    addError(key: string, error: string): boolean {
        if (key !== 'type' && key !== 'subtype') {
            return false;
        }
        this.errorsWrapper.appendChild(errorAlert(error));
        this.triggerStepError();
        return true;
    }

    triggerStepError() : void {
        this.hasError = true;
        this.element.classList.add("is-invalid");
    }

    clearStepError() : void {
        this.hasError = false;
        this.element.classList.remove("is-invalid");
    }

    validate(): boolean {
        return false;
    }

    initEventListeners() : void {}
}

export class BookingTypeStepPublic extends BaseBookingTypeStep implements BookingTypeStep, CollapsibleStep {
    result: CollapseGroup;
    select: CollapseGroup;

    private subtypes: { [index: number]: CollapseGroup } = {};
    private subtypeDescriptionsCollapse: { [index: number]: Collapse } = {};

    constructor(wrapper: HTMLElement) {
        super();
        this.element = wrapper;

        const resultElement = wrapper.querySelector<HTMLDivElement>(".step-result");
        this.result = {
            element: resultElement,
            collapse: Collapse.getOrCreateInstance(resultElement, { toggle: false })
        };

        const selectElement = wrapper.querySelector<HTMLDivElement>(".step-select");
        this.select = {
            element: selectElement,
            collapse: Collapse.getOrCreateInstance(selectElement, { toggle: false })
        };

        for (const subtype of (this.element.querySelectorAll<HTMLElement>(".subtype-select"))) {
            const id = parseInt(subtype.dataset.id);
            this.subtypes[id] = {
                element: subtype,
                collapse: Collapse.getOrCreateInstance(subtype, {toggle: false})
            };
        }
        const subtypeDescriptions: NodeListOf<HTMLElement> = this.element.querySelectorAll(".subtype-description");
        subtypeDescriptions.forEach(elem => {
            const id = parseInt(elem.dataset.id);
            this.subtypeDescriptionsCollapse[id] = new Collapse(elem, {toggle: false});
        });

        this.errorsWrapper = this.element.querySelector<HTMLDivElement>(".errors");
    }

    initEventListeners() : void {
        super.initEventListeners();
        for(const input of this.element.querySelectorAll<HTMLInputElement>('input.type-input')) {
            input.addEventListener('change', () => {
                this.triggerTypeChange();
            });
        }
        this.triggerTypeChange();

        for(const input of this.element.querySelectorAll<HTMLInputElement>('input.subtype-input')) {
            input.addEventListener('change', () => {
                this.triggerSubTypeChange();
            });
        }
        this.triggerSubTypeChange();

        this.select.element.querySelector(".next-step").addEventListener("click", () => {
          this.dispatchEvent('next-step', undefined);
        });

        this.result.element.querySelector(".edit").addEventListener("click", () => {
          this.dispatchEvent('edit', undefined);
        });
    }

    getSelectedSubType(): SubTypeInfo | null {
        const type = this.getSelectedType();
        if (!type) {
            return null;
        }
        const input = this.subtypes[type.id].element.querySelector<HTMLInputElement>("input:checked");
        if (!input) {
            return null;
        }
        return {
            id: parseInt(input.value),
            name: input.dataset.name ?? '',
            min: parseInt(input.dataset.min ?? '0'),
            max: parseInt(input.dataset.max ?? '0'),
            singleInput: 'singleInput' in input.dataset,
            slotPreset: input.dataset.slotPreset ?? null,
            input,
        };
    }

    getSelectedType(): TypeInfo | null {
        const input = this.element.querySelector<HTMLInputElement>("input.type-input:checked");
        if (!input) {
            return null;
        }
        return {
            id: parseInt(input.value),
            limit: parseInt(input.dataset.limit),
            name: input.dataset.name ?? '',
            openable: (input.dataset.openable ?? '0') === "1",
            openableMin: parseInt(input.dataset.openableMin ?? '0'),
            openableDescription: input.dataset.openableDescription ?? '',
            input,
        };
    }

    hide(): void {
        this.select.collapse.hide();
        this.result.collapse.show();
    }

    show(): void {
        this.select.collapse.show();
        this.result.collapse.hide();
    }

    private triggerSubTypeChange() {
        const selectedSubType = this.getSelectedSubType();
        this.dispatchEvent("subtype-change", selectedSubType);
        if (!selectedSubType) {
            return;
        }

        this.toggleSubtypeDescription(selectedSubType.id);
    }

    private triggerTypeChange() {
        const selectedType = this.getSelectedType();
        this.dispatchEvent("type-change", selectedType);
        if (!selectedType) {
            return;
        }

        const subtypeCount = this.subtypes[selectedType.id].element.querySelectorAll("input").length;

        this.select.element.querySelector<HTMLElement>(".continue").classList.remove("d-none");
        this.result.element.querySelector<HTMLElement>(".selected").innerText = selectedType.name;
        const selectedSubType = this.getSelectedSubType();
        if (subtypeCount > 1 && selectedSubType) {
            this.result.element.querySelector<HTMLElement>(".selected").innerText += " - " + selectedSubType.name;
        }
        // Display subtype
        Object.values(this.subtypes).forEach(value => {
            value.collapse.hide();
        });
        this.subtypes[selectedType.id].collapse.show();
        if (selectedSubType) {
            selectedSubType.input.dispatchEvent(new Event("change"));
        }
    }

    private toggleSubtypeDescription(id: number) {
        Object.values(this.subtypeDescriptionsCollapse).forEach(collapse => {
            collapse.hide();
        });
        this.subtypeDescriptionsCollapse[id].show();
    }
}

export class BookingTypeStepAdmin extends BaseBookingTypeStep implements BookingTypeStep {
    private readonly type : TypeInfo;

    constructor(wrapper: HTMLElement) {
        super();
        this.element = wrapper;

        this.type = {
            id: parseInt(this.element.dataset.id),
            limit: parseInt(this.element.dataset.limit),
            name: this.element.dataset.name ?? '',
            openable: (this.element.dataset.openable ?? '0') === "1",
            openableMin: parseInt(this.element.dataset.openableMin ?? '0'),
            openableDescription: this.element.dataset.openableDescription ?? '',
            input: this.element.querySelector<HTMLInputElement>('input.type-input'),
        }

        this.errorsWrapper = this.element.querySelector<HTMLDivElement>(".errors");
    }

    initEventListeners() : void {
        for(const input of this.element.querySelectorAll<HTMLInputElement>('input.subtype-input')) {
            input.addEventListener('change', () => {
                const selectedSubType = this.getSelectedSubType();
                this.dispatchEvent('subtype-change', selectedSubType);
                if (!selectedSubType) {
                    return;
                }
            });
        }
    }

    getSelectedSubType(): SubTypeInfo | null {
        const input = this.element.querySelector<HTMLInputElement>("input:checked");
        if (!input) {
            return null;
        }
        return {
            id: parseInt(input.value),
            name: input.dataset.name ?? '',
            min: parseInt(input.dataset.min ?? '0'),
            max: parseInt(input.dataset.max ?? '0'),
            singleInput: 'singleInput' in input.dataset,
            slotPreset: input.dataset.slotPreset ?? null,
            input,
        };
    }

    getSelectedType(): TypeInfo | null {
        return this.type;
    }

}