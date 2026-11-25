export type GroupLayout = 'list' | 'grid' | 'compact';

export type GroupOption = {
    id: number;
    parent_id: number | null;
    name: string;
    path: string;
    depth: number;
    color: string | null;
    layout: GroupLayout;
    collapsed: boolean;
    position: number;
    checks_count?: number;
    channel_ids?: number[];
};
