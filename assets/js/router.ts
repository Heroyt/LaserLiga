interface PageInfo {
	type: 'GET' | 'POST',
	routeName?: string,
	path: string[]
}

const resultsReloadPages: { [index: string]: string[] } = {
	'games-list': [],
	'results': ['results'],
	'results-game': ['results'],
};

export default function route(pageInfo: PageInfo): void {
	switch (pageInfo.routeName ?? '') {
		case 'game':
		case 'game-alias':
			import(
				/* webpackChunkName: "results" */
				'./pages/results'
				).then(module => {
				module.default();
			});
			break;
		case 'admin-arenas-edit':
			import(
				/* webpackChunkName: "admin-arenas-edit" */
				'./pages/admin/arenaEdit'
				).then(module => {
				module.default();
			});
			break;
		case 'arenas-detail':
			import(
				/* webpackChunkName: "arenas-detail" */
				'./pages/arena'
				).then(module => {
				module.default();
			});
			break;
		case 'group-results':
			import(
				/* webpackChunkName: "group-results" */
				'./pages/resultsGroup'
				).then(module => {
				module.default();
			});
			break;
		case 'find-my-games':
			import(
				/* webpackChunkName: "find-my-games" */
				'./pages/user/findGames'
				).then(module => {
				module.default();
			});
			break;
		case 'dashboard':
		case 'public-profile':
			import(
				/* webpackChunkName: "user-profile" */
				'./pages/user/profile'
				).then(module => {
				module.default();
			});
			break;
		case 'my-game-history':
		case 'player-game-history':
			import(
				/* webpackChunkName: "player-game-history" */
				'./pages/user/history'
				).then(module => {
				module.default();
			});
			break;
		case 'player-leaderboard':
		case 'player-leaderboard-arena':
			import(
				/* webpackChunkName: "player-leaderboard" */
				'./pages/user/leaderboard'
				).then(module => {
				module.default();
			});
			break;
		case 'tournament-register':
		case 'tournament-register-process':
		case 'tournament-register-update':
		case 'tournament-register-update-process':
			import(
				/* webpackChunkName: "tournament-register" */
				'./pages/tournament/register'
				).then(module => {
				module.default();
			});
			break;
	}
}