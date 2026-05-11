import { Collapse } from "bootstrap";

export interface EventListenerInterface<EventMap = Record<string, any>> {
    addEventListener<K extends keyof EventMap>(event : K, callback : (arg: EventMap[K]) => void) : void;
    removeEventListener<K extends keyof EventMap>(event : K, callback : (arg: EventMap[K]) => void) : void;
    dispatchEvent<K extends keyof EventMap>(event : K, arg: EventMap[K]) : void;
}

export class EventListener<EventMap = Record<string, any>> implements EventListenerInterface<EventMap> {
    protected listeners = new Map<keyof EventMap, Set<(arg: any) => void>>;

    addEventListener<K extends keyof EventMap>(event: K, callback: (arg: EventMap[K]) => void) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, new Set());
        }
        this.listeners.get(event)!.add(callback);
    }

    removeEventListener<K extends keyof EventMap>(event: K, callback: (arg: EventMap[K]) => void) {
        if (this.listeners.has(event)) {
            this.listeners.get(event)!.delete(callback);
        }
    }

    dispatchEvent<K extends keyof EventMap>(event: K, arg: EventMap[K]) {
        console.log({event, obj: this, listeners: this.listeners.get(event)?.size});
        if (this.listeners.has(event)) {
            for (const callback of this.listeners.get(event)!) {
                callback(arg);
            }
        }
    }
}

export interface CollapseGroup {
    element: HTMLElement,
    collapse: Collapse
}

export interface CollapsibleStep {
    select: CollapseGroup,
    result: CollapseGroup,

    hide() : void;
    show() : void;
}


export function isCollapsibleStep(step: any) : step is CollapsibleStep {
    return 'select' in step && 'result' in step && 'hide' in step && 'show' in step;
}

export interface BookingStep<EventMap = Record<string, any>> extends EventListenerInterface<EventMap>{
    element : HTMLElement;
    hasError : boolean;
    initEventListeners() : void;
    addError(key : string, error : string) : boolean;
    triggerStepError() : void;
    clearStepError() : void;
    validate() : boolean;
}

export function errorAlert(error : string) : HTMLDivElement {
    const alert = document.createElement("div");
    alert.classList.add("alert", "alert-danger");
    alert.innerText = error;
    return alert;
}