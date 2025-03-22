export function initNativeDialog(dialog : HTMLDialogElement) {
    const closeBtns = dialog.querySelectorAll<HTMLElement>('.close');

    for (const closeBtn of closeBtns) {
        closeBtn.addEventListener('click', () => {
            dialog.close();
        });
    }

    // Close the dialog when clicking outside of it
    dialog.addEventListener('click', (e) => {
        const rect  = dialog.getBoundingClientRect();

        if (
            e.clientX < rect.left
            || e.clientX > rect.right
            || e.clientY < rect.top
            || e.clientY > rect.bottom
        ) {
            dialog.close();
        }
    });
}