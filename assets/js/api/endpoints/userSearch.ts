import {UserSearchData} from "../../interfaces/userSearchData";
import {fetchGet} from "../client";

export type UserSearchResponse = UserSearchData[];

export async function findUsers(search: string, noMail: boolean = false): Promise<UserSearchResponse> {
    const searchParams = new URLSearchParams({search});
    if (noMail) {
        searchParams.append('nomail', '1');
    }
    return fetchGet('/players/find', searchParams);
}