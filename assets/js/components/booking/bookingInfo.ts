import { BookingStep, CollapseGroup, CollapsibleStep, EventListener } from "./bookingStep";
import { Collapse } from "bootstrap";
import { memo } from "../../api/client";
import { getSubtypeFields, getSubtypeInfo, getTerms } from "../../api/endpoints/booking";
import { initTooltips } from "../../functions";
import { SubTypeInfo, TypeInfo } from "./bookingTypeSelect";
import { validateInput } from "../../pages/booking/validators";

declare global {
    const fieldValues : { subtype:number, values:Record<number,string|string[]|boolean> }|null;
};

export type BookingInfoEvents = {
    "edit": void;
    'change' : void;
}

const memoizedGetTerms = memo(getTerms);
const memoizedGetSubtypeInfo = memo(getSubtypeInfo);
const memoizedGetSubtypeFields = memo(getSubtypeFields);

export interface BookingInfoStep extends BookingStep<BookingInfoEvents> {
    setType(type: TypeInfo) : void;
    setSubType(type: SubTypeInfo) : void;
}

export abstract class BaseBookingInfoStep extends EventListener implements BookingStep<BookingInfoEvents> {
    hasError: boolean = false;
    protected errorsWrapper: HTMLDivElement;
    protected subtypeWrapper: HTMLElement;

    protected constructor(
        public readonly element: HTMLElement,
    ) {
        super();

        this.errorsWrapper = this.element.querySelector(".errors");
        this.subtypeWrapper = this.element.querySelector(".subtype-info");
    }

    addError(key: string, error: string): boolean {
        if (key.match(/term-\d+/)) {
            const termInput = this.element.querySelector<HTMLInputElement>(`#${key}`);
            termInput.classList.add("is-invalid");
            const termFeedback = termInput.parentElement.querySelector(".invalid-feedback") as HTMLDivElement | undefined;
            if (termFeedback) {
                termFeedback.innerHTML = "";
                console.log(termFeedback, error);
                termFeedback.innerHTML = error;
            }
            this.triggerStepError();
            return true;
        }
        return false;
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
        const inputs: NodeListOf<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement> = this.element.querySelectorAll("input.is-invalid,select.is-invalid,textarea.is-invalid");

        return inputs.length === 0;
    }

    initEventListeners() {
        this.initInfoEventListeners();
    }

    protected initInfoEventListeners() {
        const inputs: NodeListOf<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement> = this.element.querySelectorAll("input:not(.listening),select:not(.listening),textarea:not(.listening)");

        for (const input of inputs) {
            input.classList.add("listening");
            input.addEventListener("change", () => {
                this.infoInputChanged(input);
            });
        }
    }

    protected infoInputChanged(input : HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement) : void {
        validateInput(input);
        if (this.validate()) {
            this.clearStepError();
        } else {
            this.triggerStepError();
        }
        this.dispatchEvent('change', undefined);
    }
}

export class BookingInfoStepPublic extends BaseBookingInfoStep implements BookingInfoStep, CollapsibleStep {
    result: CollapseGroup;
    select: CollapseGroup;

    private readonly termsWrapper : HTMLDivElement;
    private readonly descriptionWrapper : HTMLDivElement;

    constructor(
        element: HTMLElement,
    ) {
        super(element);

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

        this.termsWrapper = this.element.querySelector<HTMLDivElement>(".terms-and-conditions");
        this.descriptionWrapper = this.element.querySelector<HTMLDivElement>(".description");
    }

    setType(type : TypeInfo) : void {
        this.fetchTerms(type.id);
    }
    setSubType(type : SubTypeInfo) : void {
        this.fetchInfo(type.id);
        this.fetchFields(type.id);
    }

    hide(): void {
        this.select.collapse.hide();
        this.result.collapse.show();
    }

    show(): void {
        this.select.collapse.show();
        this.result.collapse.hide();
    }

    private fetchTerms(id: number): void {
        memoizedGetTerms(id)
            .then(response => {
                this.termsWrapper.innerHTML = response;
                initTooltips(this.termsWrapper);
                this.termsWrapper.querySelectorAll("input").forEach(input => {
                    input.addEventListener("change", () => {
                        input.classList.remove("is-invalid");
                    });
                });
            });
    }

    private fetchInfo(id: number): void {
        memoizedGetSubtypeInfo(id)
            .then(response => {
                this.descriptionWrapper.innerHTML = response;
            });
    }

    private fetchFields(id: number): void {
        memoizedGetSubtypeFields(id)
            .then(response => {
                this.subtypeWrapper.innerHTML = response;
                initTooltips(this.subtypeWrapper);
                this.initInfoEventListeners();
            })
            .catch((e) => {
                console.error(e);
                this.subtypeWrapper.innerHTML = "";
            });
    }
}

export class BookingInfoStepAdmin extends BaseBookingInfoStep implements BookingInfoStep {
    private fieldValues = new Map<number, Record<string,string|string[]|boolean>>()
    private activeSubtype : number|null = null;

    constructor(
        element: HTMLElement,
    ) {
        super(element);

        if (fieldValues) {
            this.fieldValues.set(fieldValues.subtype, fieldValues.values);
            this.activeSubtype = fieldValues.subtype;
        }
    }
    setType(type : TypeInfo) : void {
    }
    setSubType(type : SubTypeInfo) : void {
        this.activeSubtype = type.id;
        this.fetchFields(type.id);
    }

    protected infoInputChanged(input : HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement) : void {
        super.infoInputChanged(input);
        if (!input.id.startsWith("sub_") || !this.activeSubtype) {
            return;
        }

        const key = input.name
            .replace("sub[", "")
            .replace("[]", "")
            .replace("]", "");
        let value: string | string[] | boolean;
        if (input instanceof HTMLInputElement && (input.type === "checkbox" || input.type === "radio")) {
            if (input.type === "checkbox" && input.name.endsWith("[]")) {
                const checkboxes = this.element.querySelectorAll<HTMLInputElement>(`input[name="${input.name}"]`);
                value = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
            } else if (input.type === 'radio') {
                value = input.value;
            } else {
                value = input.checked;
            }
        } else if (input instanceof HTMLSelectElement && input.multiple) {
            value = Array.from(input.selectedOptions).map(option => option.value);
        } else {
            value = input.value;
        }

        const values = this.fieldValues.get(this.activeSubtype) ?? {};
        values[key] = value;
        console.log(key, this.activeSubtype, values);
        this.fieldValues.set(this.activeSubtype, values);
    }

    private fetchFields(id: number): void {
        memoizedGetSubtypeFields(id, true)
            .then(response => {
                this.subtypeWrapper.innerHTML = response;
                initTooltips(this.subtypeWrapper);
                this.initInfoEventListeners();
                this.setFieldValues(id);
            })
            .catch((e) => {
                console.error(e);
                this.subtypeWrapper.innerHTML = "";
            });
    }

    private setFieldValues(id: number) : void {
        if (!this.fieldValues.has(id)) {
            return;
        }

        const values = this.fieldValues.get(id);
        if (!values) {
            return;
        }

        Object.entries(values).forEach(([key, value]) => {
            const inputs = this.element.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(
                value instanceof Array ? `[name="sub[${key}][]"]` : `[name="sub[${key}]"]`
            );
            if (inputs.length === 0) {
                return;
            }

            for (const input of inputs) {
                if (input instanceof HTMLInputElement && (input.type === "checkbox" || input.type === "radio")) {
                    if (value instanceof Array) {
                        input.checked = value.includes(input.value);
                    } else if (input.type === 'radio') {
                        input.checked = input.value === value;
                    }  else {
                        input.checked = !!value;
                    }
                } else if (input instanceof HTMLSelectElement && input.multiple && value instanceof Array) {
                    for (const option of input.options) {
                        option.selected = value.includes(option.value);
                    }
                } else {
                    input.value = value as string;
                }
            }
        });
    }
}