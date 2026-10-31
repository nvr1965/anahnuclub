import { RefObject, useEffect } from 'react';

/**
 * Keeps a full-screen (position: fixed) panel inside the part of the screen the on-screen keyboard leaves visible.
 * Phone browsers that only shrink the visual viewport (Safari, older Chrome) would otherwise scroll the page up
 * when the keyboard opens, and the panel's header would slide off the top.
 */
export function useKeyboardViewport(ref: RefObject<HTMLElement | null>, query = '(max-width: 767px)') {
    useEffect(() => {
        const vv = window.visualViewport;
        const el = ref.current;
        if (!vv || !el || !window.matchMedia(query).matches) return;

        const fit = () => {
            el.style.top = `${vv.offsetTop}px`;
            el.style.height = `${vv.height}px`;
            el.style.bottom = 'auto';
            // Undo the scroll the browser makes to bring the focused field into view: the panel already fits.
            if (window.scrollY) window.scrollTo(0, 0);
        };
        fit();
        vv.addEventListener('resize', fit);
        vv.addEventListener('scroll', fit);
        return () => {
            vv.removeEventListener('resize', fit);
            vv.removeEventListener('scroll', fit);
            el.style.top = el.style.height = el.style.bottom = '';
        };
    }, [ref, query]);
}
