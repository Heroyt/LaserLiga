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
	}
}