export async function fetchPost(path: string, body: string | FormData | object | URLSearchParams = '') {
    const options: RequestInit = {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
    };

    if (body instanceof FormData) {
        options.body = body;
        // @ts-ignore
        options.headers['Content-Type'] = 'multipart/form-data';
    } else if (typeof (body) === 'object') {
        options.body = JSON.stringify(body);
        // @ts-ignore
        options.headers['Content-Type'] = 'application/json';
    } else {
        options.body = body;
    }

    const response = await fetch(path, options);
    const type = response.headers.get('Content-Type');
    return processResponse(type, response);
}

/**
 * Send a GET request to a path with optional get parameters.
 *
 * @param path
 * @param params
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
    const type = response.headers.get('Content-Type');
    return processResponse(type, response);
}

async function processResponse(type: string, response: Response) {
    switch (type) {
        case 'application/json':
            return response.json();
        default:
            return response.text();
    }
}