import { fetchGet, fetchPost, SuccessResponse } from "../client";
import { CalendarConfiguration, SubTypeData } from "../../pages/booking/interfaces";
import { prefixPathWithLang } from "../../functions";

export async function getCalendar(arenaSlug : string, config : CalendarConfiguration) : Promise<string> {
    return fetchPost(prefixPathWithLang(`/rezervace/${arenaSlug}/calendar`), config);
}

export async function saveBooking(arenaSlug : string, data : FormData) : Promise<SuccessResponse<{redirect?:string, booking?:number}>> {
    return fetchPost(prefixPathWithLang(`/rezervace/${arenaSlug}`), data);
}

export async function getSubtype(subtype : number) : Promise<SubTypeData> {
    return fetchGet(prefixPathWithLang(`/rezervace/subtype/${subtype}`));
}

export async function getSubtypeDescription(subtype : number) : Promise<string> {
    return fetchGet(prefixPathWithLang(`/rezervace/subtype/${subtype}/description`));
}

export async function getSubtypeDatetime(subtype : number) : Promise<string> {
    return fetchGet(prefixPathWithLang(`/rezervace/subtype/${subtype}/datetime`));
}

export async function getSubtypeInfo(subtype : number) : Promise<string> {
    return fetchGet(prefixPathWithLang(`/rezervace/subtype/${subtype}/info`));
}

export async function getSubtypeFields(subtype : number, includePrivate : boolean = false) : Promise<string> {
    return fetchGet(prefixPathWithLang(`/rezervace/subtype/${subtype}/fields`), {private : includePrivate ? '1' : '0'});
}

export async function getTerms(type : number) : Promise<string> {
    return fetchGet(prefixPathWithLang(`/rezervace/type/${type}/terms`));
}
