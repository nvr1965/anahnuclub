/**
 * Puts the cursor into the search box the current style shows on screen and lights it up for a moment.
 * Returns false when no box is visible (Stream between lg and xl, Showcase between md and xl), so the caller can open
 * the search page instead.
 */
export function focusSearchBox(): boolean {
    const box = [...document.querySelectorAll<HTMLInputElement>('input[data-search-box]')].find((el) => el.getClientRects().length > 0);
    if (!box) return false;

    box.focus({ preventScroll: true });
    box.select();
    const frame = box.closest('form') ?? box;
    frame.classList.add('search-box-flash');
    window.setTimeout(() => frame.classList.remove('search-box-flash'), 1600);
    return true;
}
