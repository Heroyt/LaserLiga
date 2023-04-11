export interface UserSearchData {
	id: number;
	nickname: string;
	code: string;
	email: string;
	stats: {
		rank: number,
		gamesPlayed: number,
	}
	connections: { type: string, identifier: string }[];
}