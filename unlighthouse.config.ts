export default {
	// example
	site: 'laserliga.cz',
    puppeteerClusterOptions: {
        maxConcurrency: 4,
    },
	scanner: {
		exclude: ['/lang/*'],
		samples: 2,
        dynamicSampling: 5,
        skipJavascript: false,
        throttle: false,
        customSampling: {
            '/user/leaderboard': {
                name: 'user-leaderboard',
            },
            '/user/(.*)': {
                name: 'user-profile',
            },
            '/user/(.*)/history': {
                name: 'user-history',
            },
            '/user/(.*)/tournaments': {
                name: 'user-tournaments',
            },
            '/arena/([0-9]+)': {
                name: 'arena-detail',
            },
            '/tournament/([0-9]+)/register': {
                name: 'tournament-register',
            },
            '/tournament/([0-9]+)': {
                name: 'tournament-detail',
            },
            '/event/(.+)/register': {
                name: 'event-register',
            },
            '/event/(.+)': {
                name: 'event-detail',
            },
            '/liga/([^/]*)/register': {
                name: 'league-register',
            },
            '/liga/([^/]*)/substitute': {
                name: 'league-register-substitute',
            },
            '/league/([0-9]+)/team/([0-9]+)': {
                name: 'league-team',
            },
            '/liga/([^/]*)': {
                name: 'league-detail',
            },
            '/game/group/([^/]*)': {
                name: 'game-group-results',
            },
            '/game/([^/]*)': {
                name: 'game-results',
            },
        }
	},
	debug: false,
}