import {fetchGet} from "../client";

export type ArenaStatModeResponse = {
    [index: string]: number
};

export type ArenaStatMusicResponse = {
    [index: string]: number
};

export async function getArenaStatsModes(arenaId: number, params: URLSearchParams): Promise<ArenaStatModeResponse> {
    return fetchGet(`/arena/${arenaId}/stats/modes`, params);
}

export async function getArenaStatsMusic(arenaId: number, params: URLSearchParams): Promise<ArenaStatMusicResponse> {
    return fetchGet(`/arena/${arenaId}/stats/music`, params);
}