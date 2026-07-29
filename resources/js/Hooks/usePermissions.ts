import { usePage } from '@inertiajs/react';
import type { Permission, SharedProps } from '@/types';

/**
 * UI-side permission check. Rendering hint only — the server re-authorizes
 * every action regardless of what the client shows (وثيقة 05 §8).
 */
export function usePermissions(): { can: (permission: Permission) => boolean } {
    const { auth } = usePage<SharedProps>().props;

    return {
        can: (permission: Permission) => auth.permissions.includes(permission),
    };
}
