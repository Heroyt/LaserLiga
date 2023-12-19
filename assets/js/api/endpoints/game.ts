import {HighlightInfo} from "../../interfaces/game";
import {fetchGet} from "../client";

export type GameHighlightResponse = HighlightInfo[];

export type GamePlayerDistributionResponse = {
    player: { [index: string]: any },
    distribution: { [index: string]: number },
    percentile: number,
    min: number,
    max: number,
    value: number,
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
    return fetchGet(`/game/${gameCode}/player/${playerId}/distribution/${distributionParameter}?dates=${dates}`);
}