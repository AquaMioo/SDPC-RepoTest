import { useEffect } from 'react';

export type Mod = 'user' | 'admin';

/**
 * Point the document at one of the design's two palettes.
 *
 * Blade stamps `data-mod` on <html> for the first paint, but Inertia visits
 * never reload the document, so moving between the admin portal and the user
 * app has to re-point it here. Layouts also set data-mod on their own root
 * element, which is what actually scopes the tokens; this only keeps <html>
 * (and therefore the overscroll ground) in step.
 */
export function useMod(mod: Mod): void {
    useEffect(() => {
        document.documentElement.dataset.mod = mod;
    }, [mod]);
}
