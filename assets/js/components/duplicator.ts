import { customFetch, CustomFetchOptions, RequestMethod, SuccessResponse } from "../api/client";
import { startLoading, stopLoading } from "../loaders";
import { triggerNotificationError } from "./notifications";

export type DuplicatorOptions = {
    template: HTMLTemplateElement|null;
    idReplace: string;
    initCallback: ((element: HTMLElement, id: number) => void)|null;
    ajaxCreateUrl: string|null;
    ajaxCreateMethod: RequestMethod;
    ajaxCreateData?: { [key: string]: any }|(() => { [key: string]: any });
    ajaxDeleteUrl: string|null;
    ajaxDeleteMethod: RequestMethod;
    childSelector: string;
    maxId?: number;
};

export type AjaxCreateResponse = SuccessResponse<{id: number, html?: string}>;

export default class Duplicator {
    options : DuplicatorOptions;

    private nextId = 0;

    constructor(
        public readonly wrapper : HTMLElement,
        public readonly addBtn : HTMLButtonElement,
        options : Partial<DuplicatorOptions> = {}
    ) {
        // Extend options with defaults
        this.options = {
            ...{
                template: null,
                idReplace: '#id#',
                initCallback: null,
                ajaxCreateUrl: null,
                ajaxCreateMethod: 'POST',
                ajaxDeleteUrl: null,
                ajaxDeleteMethod: 'DELETE',
                childSelector: '.duplicator-child',
            },
            ...options
        }

        // Find next ID
        if (this.options.maxId) {
            this.nextId = options.maxId + 1;
        }
        else {
            for (const child of this.getChildren()) {
                const id = parseInt(child.dataset.id ?? '0');
                if (id > this.nextId) {
                    this.nextId = id;
                }
            }
            this.nextId++;
        }

        // Init all children
        for (const child of this.getChildren()) {
            this.initChild(child);
        }

        this.addBtn.addEventListener('click', e => {
            e.preventDefault();
            this.addNewChild();
        });
    }

    public getChildren(): NodeListOf<HTMLElement> {
        return this.wrapper.querySelectorAll(this.options.childSelector);
    }

    public addNewChild(): void {
        if (this.options.ajaxCreateUrl) {
            this.addChildFromAjax();
            return;
        }
        if (this.options.template) {
            this.addChildFromTemplate();
            return;
        }
        throw new Error('No template or AJAX URL provided for duplicator');
    }

    private addChildFromTemplate(id : number|null = null): void {
        if (!this.options.template) {
            throw new Error('No template provided for duplicator');
        }

        if (!id) {
            id = this.nextId;
            this.nextId++;
        }

        const tmp = document.createElement('div');
        tmp.innerHTML = this.options.template!.innerHTML.replaceAll(this.options.idReplace, id.toString());
        const newChild = tmp.firstElementChild as HTMLElement;
        this.wrapper.appendChild(newChild);

        this.initChild(newChild);
    }

    private async addChildFromAjax() : Promise<void> {
        try {
            startLoading();
            const options : CustomFetchOptions = {};
            if (this.options.ajaxCreateData) {
                if (typeof this.options.ajaxCreateData === 'function') {
                    options.body = this.options.ajaxCreateData();
                } else {
                    options.body = this.options.ajaxCreateData;
                }
                options.headers = {
                    'Content-Type': 'application/json'
                };
            }
            const response = await customFetch<AjaxCreateResponse>(
                this.options.ajaxCreateUrl,
                this.options.ajaxCreateMethod,
                options,
            );

            this.nextId = response.values.id + 1;
            if (response.values.html) {
                const tmp = document.createElement('div');
                tmp.innerHTML = response.values.html;
                const newChild = tmp.firstElementChild as HTMLElement;
                this.wrapper.appendChild(newChild);

                this.initChild(newChild);
            }
            else {
                this.addChildFromTemplate(response.values.id);
            }
            stopLoading(true);
        } catch (error) {
            stopLoading(false);
            await triggerNotificationError(error);
            console.error('Error while creating new duplicator child:', error);
        }
    }

    private initChild(child: HTMLElement): void {
        const id = parseInt(child.dataset.id ?? '0');
        if (isNaN(id)) {
            console.warn('Child element does not have a valid ID:', child);
            return;
        }

        // Call a custom initialization callback if provided
        if (this.options.initCallback) {
            this.options.initCallback(child, id);
        }

        // Initialize delete buttons
        const deleteBtns = child.querySelectorAll<HTMLButtonElement>('.delete, [data-action="delete"]');
        for (const deleteBtn of deleteBtns) {
            deleteBtn.addEventListener('click', async (e) => {
                e.preventDefault();

                if (this.options.ajaxDeleteUrl) {
                    try {
                        startLoading();
                        await customFetch(this.options.ajaxDeleteUrl.replace(this.options.idReplace, id.toString()), this.options.ajaxDeleteMethod);
                        stopLoading(true);
                    } catch (error) {
                        stopLoading(false);
                        console.error('Error while deleting duplicator child:', error);
                        return;
                    }
                }

                child.remove();
            });
        }
    }
}