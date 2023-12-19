import {fetchGet, fetchPost} from "../client";

export type UserCompareResponse = {
    gameCount: number,
    gameCountTogether: number,
    gameCountEnemy: number,
    gameCountEnemyTeam: number,
    gameCountEnemySolo: number,
    winsTogether: number,
    lossesTogether: number,
    drawsTogether: number,
    winsEnemy: number,
    lossesEnemy: number,
    drawsEnemy: number,
    hitsEnemy: number,
    deathsEnemy: number,
    hitsTogether: number,
    deathsTogether: number
};

export type UserUnsetMeResponse = { status: string };
export type UserSetMeResponse = { status: string };
export type UserSetGroupMeResponse = { status: string };
export type UserSetAllMeResponse = { status: string };
export type UserSetNotMeResponse = { status: string };

export async function getUserCompare(userCode: string): Promise<UserCompareResponse> {
    return fetchGet(`/user/${userCode}/compare`);
}

export async function userUnsetMe(gameCode: string): Promise<UserUnsetMeResponse> {
    return fetchPost('/user/player/unsetme', {code: gameCode})
}

export async function userSetMe(playerId: number, system: string): Promise<UserSetMeResponse> {
    return fetchPost('/user/player/setme', {id: playerId, system})
}

export async function userSetGroupMe(groupId: number): Promise<UserSetGroupMeResponse> {
    return fetchPost('/user/player/setgroupme', {id: groupId})
}

export async function userSetAllMe(): Promise<UserSetAllMeResponse> {
    return fetchPost('/user/player/setallme')
}

export async function userSetNotMe(playerId: number): Promise<UserSetNotMeResponse> {
    return fetchPost('/user/player/setnotme', {id: playerId});
}
