import type { GroupOption } from '@/types/groups';

/** Ids of a group and all of its descendants. */
export function groupWithDescendantIds(
    groups: GroupOption[],
    id: number,
): number[] {
    const ids = [id];

    for (let i = 0; i < ids.length; i++) {
        for (const g of groups) {
            if (g.parent_id === ids[i] && !ids.includes(g.id)) {
                ids.push(g.id);
            }
        }
    }

    return ids;
}

export const DEFAULT_GROUP_COLOR = '#94a3b8';
