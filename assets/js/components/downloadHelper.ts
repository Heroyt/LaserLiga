export function initDownloadButton(button : HTMLAnchorElement) {
    const url = new URL(button.href);
    const notDownloadingContent = button.querySelector<HTMLSpanElement>('.not-downloading');
    const downloadingContent = button.querySelector<HTMLSpanElement>('.downloading');
    let downloadCheckTimer : NodeJS.Timeout;

    let manualDownload : HTMLElement|null = null;
    if (button.dataset.manualDownload) {
        manualDownload = document.querySelector<HTMLElement>(button.dataset.manualDownload);
    }

    // Update the href adding the download token to the query parameters
    const token = generateDownloadToken();
    url.searchParams.set('token', token);
    button.href = url.toString();

    button.addEventListener('click', (event) => {
        // Start waiting for the download to finish
        button.ariaDisabled = 'true';
        button.classList.add('disabled');
        if (notDownloadingContent && downloadingContent) {
            notDownloadingContent.classList.add('d-none');
            downloadingContent.classList.remove('d-none');
        }

        if (manualDownload) {
            manualDownload.classList.remove('d-none');
        }

        let attempts = 'timeout' in button.dataset ? parseInt(button.dataset.timeout) : 60;
        downloadCheckTimer = setInterval(() => {
            const cookieToken = getCookie('downloadToken');

            if (token === cookieToken || attempts <= 0) {
                downloadFinished();
                return;
            }

            attempts--;
        }, 1000);
    });

    function generateDownloadToken() {
        const token = Math.random().toString(36).substring(2, 15);
        button.dataset.downloadToken = token;
        return token;
    }

    function downloadFinished() : void {
        if (downloadCheckTimer) {
            clearInterval(downloadCheckTimer);
        }
        button.ariaDisabled = 'false';
        button.classList.remove('disabled');
        if (notDownloadingContent && downloadingContent) {
            notDownloadingContent.classList.remove('d-none');
            downloadingContent.classList.add('d-none');
        }
        expireCookie('downloadToken');

        // Create a new token for the next download
        const newToken = generateDownloadToken();
        url.searchParams.set('token', newToken);
        button.href = url.toString();
    }
}

function getCookie(name : string) : string {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return '';
}

function expireCookie(name : string) : void {
    document.cookie = encodeURIComponent(name) + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
}