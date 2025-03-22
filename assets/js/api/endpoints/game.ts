import {HighlightInfo} from "../../interfaces/game";
import {fetchGet, fetchPost, SuccessResponse} from "../client";

export type GameHighlightResponse = HighlightInfo[];

export type GamePlayerDistributionResponse = {
    player: { [index: string]: any },
    distribution: { [index: string]: number },
    percentile: number,
    min: number,
    max: number,
    value: number,
    valueReal: number,
};

export async function getGameHighlights(gameCode: string): Promise<GameHighlightResponse> {
    return fetchGet(`/game/${gameCode}/highlights`);
}

export async function getGameTeamResults(gameCode: string, teamId: number): Promise<string> {
    return fetchGet(`/game/${gameCode}/team/${teamId}`);
}

export async function getGamePlayerResults(gameCode: string, playerId: number): Promise<string> {
    return fetchGet(`/game/${gameCode}/player/${playerId}`);
}

export async function getGamePlayerDistribution(gameCode: string, playerId: number, distributionParameter: string, dates: string): Promise<GamePlayerDistributionResponse> {
    return fetchGet(`/game/${gameCode}/player/${playerId}/distribution/${distributionParameter}`, {dates});
}

export async function makePhotosPublic(gameCode: string): Promise<SuccessResponse> {
    return fetchPost(`/game/${gameCode}/photos/public`, {});
}

export async function makePhotosHidden(gameCode: string): Promise<SuccessResponse> {
    return fetchPost(`/game/${gameCode}/photos/hidden`, {});
}

export async function makePhotosPublicGroup(groupI: string): Promise<SuccessResponse> {
    return fetchPost(`/game/group/${groupI}/photos/public`, {});
}

export async function makePhotosHiddenGroup(groupI: string): Promise<SuccessResponse> {
    return fetchPost(`/game/group/${groupI}/photos/hidden`, {});
}