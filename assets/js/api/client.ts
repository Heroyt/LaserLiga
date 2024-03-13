export type RequestMethod = 'POST' | 'GET' | 'PUT' | 'DELETE';
export type CustomFetchOptions = {
    body?: string | FormData | object | URLSearchParams,
    params?: { [key: string]: any } | URLSearchParams | string,
    headers?: Record<string, string>,
};
export type FormSaveResponse = {
    status?: 'ok' | 'error' | string,
    success?: boolean,
    message?: string,
    errors?: string | string[],
}

export class ResponseError extends Error {
    public response: Response;

    constructor(response: Response) {
        super(`Request failed with an error: ${response.statusText}`);
        this.response = response;
    }

    private _data: any;

    get data(): Promise<any> {
        return this.getDataFromResponse();
    }

    async getDataFromResponse(): Promise<any> {
        if (this._data) {
            return this._data;
        }

        this._data = await processResponse(this.response.headers.get('Content-Type'), this.response);
        return this._data;
    }

}

/**
 * Call a fetch method with some pre-processing
 * @param path
 * @param method
 * @param options
 * @throws ResponseError
 */
export async function customFetch(path: string, method: RequestMethod, options: CustomFetchOptions = {}) {
    const response = await prepareFetch(path, method, options);
    if (!response.ok) {
        throw new ResponseError(response);
    }
    const type = response.headers.get('Content-Type');
    return processResponse(type, response);
}


/**
 * Call a fetch POST method with some pre-processing
 * @param path
 * @param body
 * @throws ResponseError
 */
export async function fetchPost(path: string, body: string | FormData | object | URLSearchParams = '') {
    const options: RequestInit = {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    if (body instanceof FormData) {
        options.body = body;
    } else if (typeof (body) === 'object') {
        options.body = JSON.stringify(body);
        // @ts-ignore
        options.headers['Content-Type'] = 'application/json';
    } else {
        options.body = body;
    }

    const response = await fetch(path, options);
    if (!response.ok) {
        throw new ResponseError(response);
    }
    const type = response.headers.get('Content-Type');
    return processResponse(type, response);
}

/**
 * Send a GET request to a path with optional get parameters.
 *
 * @param path
 * @param params
 * @throws ResponseError
 */
export async function fetchGet(path: string, params: { [key: string]: any } | URLSearchParams | string = {}) {
    if (params) {
        const searchParams = new URLSearchParams(params);
        path += '?' + searchParams.toString();
    }
    const response = await fetch(
        path,
        {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            }
        }
    );
    if (!response.ok) {
        throw new ResponseError(response);
    }
    const type = response.headers.get('Content-Type');
    return processResponse(type, response);
}

export async function processResponse(type: string, response: Response) {
    switch (type) {
        case 'application/json':
            return response.json();
        default:
            return response.text();
    }
}

export async function prepareFetch(path: string, method: RequestMethod, options: CustomFetchOptions = {}): Promise<Response> {
    const requestOptions: RequestInit = {
        method: method,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
    };

    if (options.params) {
        const searchParams = new URLSearchParams(options.params);
        path += '?' + searchParams.toString();
    }

    if (options.body) {
        if (options.body instanceof FormData) {
            requestOptions.body = options.body;
            // @ts-ignore
            requestOptions.headers['Content-Type'] = 'multipart/form-data';
        } else if (typeof (options.body) === 'object') {
            requestOptions.body = JSON.stringify(options.body);
            // @ts-ignore
            requestOptions.headers['Content-Type'] = 'application/json';
        } else {
            requestOptions.body = options.body;
        }
    }

    if (options.headers) {
        Object.entries(options.headers).forEach(([key, value]) => {
            // @ts-ignore
            requestOptions.headers[key] = value;
        });
    }
    return fetch(path, requestOptions);
}