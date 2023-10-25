export default function initCountUp(elem: HTMLElement | Document | null = null) {
    if (!elem) {
        elem = document;
    }

    const observer = new IntersectionObserver(startCountUp);

    (elem.querySelectorAll('.countUp') as NodeListOf<HTMLElement>).forEach(countUpElem => {
        const start = parseInt(countUpElem.dataset.start ?? '0');
        countUpElem.innerText = start.toString();
        observer.observe(countUpElem);
    });

    function startCountUp(entries: IntersectionObserverEntry[], observer: IntersectionObserver) {
        entries.forEach(entry => {
            const countUpElem = entry.target as HTMLElement;
            if (countUpElem.classList.contains('started') || !entry.isIntersecting) {
                return;
            }
            countUpElem.classList.add('started')
            const start = parseInt(countUpElem.dataset.start ?? '0');
            const target = parseInt(countUpElem.dataset.target ?? '0');
            const duration = parseInt(countUpElem.dataset.duration ?? '1000');
            const countUp = () => {
                let startTimestamp: number = null;
                const step = (timestamp: number) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    countUpElem.innerText = Math.floor(progress * (target - start) + start).toLocaleString(undefined);
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
            };
            countUp();
        });
    }
}