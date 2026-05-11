import { BookingTypeStep, SubTypeInfo, TypeInfo } from "../../components/booking/bookingTypeSelect";
import { BookingStep, errorAlert, isCollapsibleStep } from "../../components/booking/bookingStep";
import { BookingCalendarStep } from "../../components/booking/bookingCalendar";
import { BookingInfoStep } from "../../components/booking/bookingInfo";

type StepName = "type" | "calendar" | "info";

export default class BookingForm {
    readonly errorsWrapper: HTMLElement;
    readonly arenaSlug: string;

    constructor(
        private readonly form: HTMLFormElement,
        private readonly typeStep: BookingTypeStep,
        private readonly calendarStep: BookingCalendarStep,
        private readonly infoStep: BookingInfoStep,
        private readonly submit: (data: FormData, obj: BookingForm) => void
    ) {
        this.arenaSlug = this.form.dataset.arena;

        this.errorsWrapper = document.getElementById("errors");

        this.initEventListeners();

        this.triggerTypeChange(this.typeStep.getSelectedType());
        this.triggerSubTypeChange(this.typeStep.getSelectedSubType());

        this.setStep('type');
    }

    get steps() {
        return new Map<StepName, BookingStep>([
            ["type", this.typeStep],
            ["calendar", this.calendarStep],
            ["info", this.infoStep],
        ]);
    }

    addError(key: string, error: string) {
        if (this.typeStep.addError(key, error)) return;
        if (this.calendarStep.addError(key, error)) return;
        if (this.infoStep.addError(key, error)) return;
        const test = parseInt(key);
        if (isNaN(test)) {
            // Input error
            const input = document.getElementById(key);
            if (input) {
                const parentSection = input.findParentElement("section");
                this.triggerStepError(parentSection.dataset.key as StepName);
                input.classList.add("is-invalid");
                const feedback: HTMLDivElement = input.parentElement.querySelector(".invalid-feedback");
                if (feedback) {
                    const msg = document.createElement("div");
                    msg.innerText = error;
                    feedback.appendChild(msg);
                }
                return;
            }
        }
        // Global error
        this.errorsWrapper.appendChild(errorAlert(error));
    }

    setStep(stepName: StepName): void {
        console.log(stepName, { type: this.typeStep.getSelectedType(), subtype: this.typeStep.getSelectedSubType() });
        switch (stepName) {
            case "type":
                if (isCollapsibleStep(this.typeStep)) {
                    this.typeStep.show();
                }
                if (isCollapsibleStep(this.calendarStep)) {
                    this.calendarStep.hide();
                }
                if (isCollapsibleStep(this.infoStep)) {
                    this.infoStep.hide();
                }
                break;
            case "calendar":
                if (isCollapsibleStep(this.typeStep)) {
                    this.typeStep.hide();
                }
                if (isCollapsibleStep(this.calendarStep)) {
                    this.calendarStep.show();
                }
                if (isCollapsibleStep(this.infoStep)) {
                    this.infoStep.hide();
                }
                break;
            case "info":
                if (!this.calendarStep.validate()) {
                    return;
                }
                if (isCollapsibleStep(this.typeStep)) {
                    this.typeStep.hide();
                }
                if (isCollapsibleStep(this.calendarStep)) {
                    this.calendarStep.hide();
                }
                if (isCollapsibleStep(this.infoStep)) {
                    this.infoStep.show();
                }
                break;
        }

        // Scroll the current step into view
        setTimeout(() => { // Timeout to let all animations finish
            this.scrollToStep(stepName);
        }, 400);
    }

    scrollToStep(stepName: StepName) {
        let top = 0;
        switch (stepName) {
            case "type":
                top = this.typeStep.element.offsetTop;
                break;
            case "calendar":
                top = this.calendarStep.element.offsetTop;
                break;
            case "info":
                top = this.infoStep.element.offsetTop;
                break;

        }

        window.scrollTo({
            behavior: "smooth",
            top: top - 80
        });
    }

    private triggerStepError(stepName: StepName) {
        switch (stepName) {
            case "type":
                this.typeStep.triggerStepError();
                break;
            case "calendar":
                this.calendarStep.triggerStepError();
                break;
            case "info":
                this.infoStep.triggerStepError();
                break;
        }
    }

    private triggerTypeChange(type: TypeInfo | null = null): void {
        if (!type) {
            return;
        }

        this.calendarStep.setType(type);
        this.infoStep.setType(type);

        const subtype = this.typeStep.getSelectedSubType();
        if (subtype) {
            this.calendarStep.setSubtype(subtype);
            this.infoStep.setSubType(subtype);
        }
    }

    private triggerSubTypeChange(type: SubTypeInfo | null = null): void {
        if (!type) {
            return;
        }

        this.calendarStep.setSubtype(type);
        this.infoStep.setSubType(type);
    }

    private initEventListeners() {
        this.typeStep.initEventListeners();
        this.typeStep.addEventListener("type-change", type => this.triggerTypeChange(type));
        this.typeStep.addEventListener("subtype-change", type => this.triggerSubTypeChange(type));
        this.typeStep.addEventListener("next-step", () => this.setStep("calendar"));
        this.typeStep.addEventListener("edit", () => this.setStep("type"));

        // Calendar events
        this.calendarStep.initEventListeners();
        this.calendarStep.addEventListener("next-step", () => this.setStep("info"));
        this.calendarStep.addEventListener("edit", () => this.setStep("calendar"));
        this.calendarStep.addEventListener("change", () => this.checkAnyInvalidInput());

        // Info events
        this.infoStep.initEventListeners();
        this.infoStep.addEventListener("edit", () => this.setStep("info"));
        this.infoStep.addEventListener("change", () => this.checkAnyInvalidInput());

        // Form handler
        this.form.addEventListener("submit", e => {
            e.preventDefault();
            this.formSubmit();
        });
    }

    private checkAnyInvalidInput() {
        const inputs: NodeListOf<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement> = this.form.querySelectorAll("input.is-invalid,select.is-invalid,textarea.is-invalid");

        if (inputs.length === 0) {
            this.errorsWrapper.innerHTML = "";
        }
    }

    /**
     * Handle form submit event
     *
     * Sends form using AJAX and if successful, redirects to the thank-you page. If there is an error, displays the error.
     *
     * @private
     */
    private formSubmit() {
        const data = new FormData(this.form);

        // Clear error messages
        this.errorsWrapper.innerHTML = "";
        this.typeStep.clearStepError();
        this.calendarStep.clearStepError();
        this.infoStep.clearStepError();
        document.querySelectorAll(".is-invalid").forEach(elem => {
            elem.classList.remove("is-invalid");
        });
        document.querySelectorAll(".invalid-feedback").forEach(elem => {
            elem.innerHTML = "";
        });

        this.submit(data, this);
    }

}