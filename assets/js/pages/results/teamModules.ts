export interface TeamModuleInterface {
    load: (body: HTMLDivElement, id: number) => Promise<void>;
    init: (wrapper: HTMLDivElement) => void;
}