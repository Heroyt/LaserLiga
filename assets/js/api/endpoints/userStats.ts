import {AchievementClaimDto, TrendData, UserStatGameCount, UserStatTrophy} from "../../interfaces/userStats";
import {fetchGet} from "../client";

export type UserStatTrendsResponse = {
    accuracy: number,
    averageShots: number,
    rank: number,
    games: TrendData,
    rankableGames: TrendData,
    sumShots: TrendData,
    sumHits: TrendData,
    sumDeaths: TrendData,
    rankOrder: TrendData
};

export type UserStatAchievementsResponse = AchievementClaimDto[];

export type UserStatRankHistoryResponse = { [index: string]: number };

export type UserStatModesResponse = { [index: string]: number };

export type UserStatGameCountsResponse = { [index: string]: UserStatGameCount };

export type UserStatOrderHistoryResponse = { [index: string]: { label: string, position: number } };

export type UserStatRadarResponse = { [index: string]: { [index: string]: number } };

export type UserStatTrophiesResponse = {
    [index: string]: UserStatTrophy
};

export async function getUserTrends(userCode: string): Promise<UserStatTrendsResponse> {
    return fetchGet(`/user/${userCode}/stats/trends`);
}

export async function getUserAchievements(userCode: string): Promise<UserStatAchievementsResponse> {
    return fetchGet(`/user/${userCode}/stats/achievements`);
}

export async function getUserRankHistory(userCode: string, limit: string = 'month'): Promise<UserStatRankHistoryResponse> {
    return fetchGet(`/user/${userCode}/stats/rank/history`, {limit});
}

export async function getUserModes(userCode: string, limit: string = 'month'): Promise<UserStatModesResponse> {
    return fetchGet(`/user/${userCode}/stats/modes`, {limit});
}

export async function getUserGameCounts(userCode: string, limit: string = 'month'): Promise<UserStatGameCountsResponse> {
    return fetchGet(`/user/${userCode}/stats/gamecounts`, {limit});
}

export async function getUserOrderHistory(userCode: string, limit: string = 'month'): Promise<UserStatOrderHistoryResponse> {
    return fetchGet(`/user/${userCode}/stats/rank/orderhistory`, {limit});
}

export async function getUserRadar(userCode: string, compare: string = ''): Promise<UserStatRadarResponse> {
    return fetchGet(`/user/${userCode}/stats/radar`, {compare});
}

export async function getUserTrophies(userCode: string, rankableOnly: boolean = false): Promise<UserStatTrophiesResponse> {
    return fetchGet(`/user/${userCode}/stats/trophies`, rankableOnly ? {rankable: 1} : {});
}