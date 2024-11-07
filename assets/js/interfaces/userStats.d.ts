export interface TrendData {
    before: number,
    now: number,
    diff: number,
}

export interface Achievement {
    id: number;
    icon: string | null;
    name: string;
    description: string;
    info: string | null;
    type: string;
    rarity: string;
    key: string | null;
    group: boolean;
    title: null | { id: number, name: string },
}

export interface AchievementClaimDto {
    achievement: Achievement;
    icon: string;
    claimed: boolean;
    code: string | null;
    dateTime: { date: string, timezone_type: number, timezone: string } | string | null;
    totalCount: number;
}

export type UserStatModeCount = { count: number, id_mode: number, modeName: string };
export type UserStatGameCount = { label: string, modes: UserStatModeCount[] };

export type UserStatTrophy = { name: string, icon: string, description: string, count: number };