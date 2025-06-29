export type Arena = {
    id: number;
    name: string;
    lat: number|null;
    lng: number|null;
    address: Address;
    web: string|null;
    contactEmail: string|null;
    contactPhone: string|null;
    hidden: boolean;
}

export type Address = {
    street: string|null;
    city: string|null;
    postCode: string|null;
    country: string|null;
}