interface PageInfo {
    type: "GET" | "POST",
    routeName?: string,
    path: string[]
}

export default function route(pageInfo: PageInfo): void {
    console.log(pageInfo);
    switch (pageInfo.routeName ?? "") {
        case "index":
            import(
                /* webpackChunkName: "index" */
                "./pages/index"
                ).then(module => {
                module.default();
            });
            break;
        case "game":
        case "game-alias":
        case "user-game":
            import(
                /* webpackChunkName: "results" */
                "./pages/results/results"
                ).then(module => {
                module.default();
            });
            break;
        case "admin-arenas-edit":
            import(
                /* webpackChunkName: "admin-arenas-edit" */
                "./pages/admin/arenaEdit"
                ).then(module => {
                module.default();
            });
            break;
        case "admin-create-game":
            import(
                /* webpackChunkName: "admin-create-game" */
                "./pages/admin/createGame"
                ).then(module => {
                module.default();
            });
            break;
        case "arenas-detail":
        case "arena-detail-stats":
        case "arena-detail-music":
        case "arena-detail-games":
        case "arena-detail-tournaments":
        case "arena-detail-info":
        case "arenas-detail-slug":
        case "arena-detail-stats-slug":
        case "arena-detail-music-slug":
        case "arena-detail-games-slug":
        case "arena-detail-tournaments-slug":
        case "arena-detail-info-slug":
            import(
                /* webpackChunkName: "arenas-detail" */
                "./pages/arena"
                ).then(module => {
                module.default();
            });
            break;
        case "group-results":
            import(
                /* webpackChunkName: "group-results" */
                "./pages/resultsGroup"
                ).then(module => {
                module.default();
            });
            break;
        case "find-my-games":
            import(
                /* webpackChunkName: "find-my-games" */
                "./pages/user/findGames"
                ).then(module => {
                module.default();
            });
            break;
        case "dashboard":
        case "public-profile":
            import(
                /* webpackChunkName: "user-profile" */
                "./pages/user/profile"
                ).then(module => {
                module.default();
            });
            break;
        case "my-game-history":
        case "player-game-history":
            import(
                /* webpackChunkName: "player-game-history" */
                "./pages/user/history"
                ).then(module => {
                module.default();
            });
            break;
        case "player-leaderboard":
        case "player-leaderboard-arena":
        case "player-leaderboard-arena-slug":
            import(
                /* webpackChunkName: "player-leaderboard" */
                "./pages/user/leaderboard"
                ).then(module => {
                module.default();
            });
            break;
        case "tournament-register":
        case "tournament-register-process":
        case "tournament-register-update":
        case "tournament-register-update-2":
        case "tournament-register-update-process":
        case "event-register":
        case "event-register-process":
        case "event-register-update":
        case "event-register-update-2":
        case "event-register-update-process":
        case "league-register":
        case "league-register-process":
        case "league-register-slug":
        case "league-register-process-slug":
        case "league-register-update":
        case "league-register-update-2":
        case "league-register-update-process":
        case "league-register-substitute":
        case "league-register-substitute-process":
        case "league-register-substitute-slug":
        case "league-register-substitute-slug-process":
            import(
                /* webpackChunkName: "tournament-register" */
                "./pages/tournament/register"
                ).then(module => {
                module.default();
            });
            break;
        case "profile":
            import(
                /* webpackChunkName: "user-settings" */
                "./pages/user/settings"
                ).then(module => {
                module.default();
            });
            break;
        case "kiosk-dashboard":
        case "kiosk-dashboard-type":
            import(
                "./pages/kiosk/dashboard"
                )
                .then(module => {
                    module.default();
                });
            break;
        case "admin-arenas-photos":
            import(
                "./pages/admin/arenaPhotos"
                )
                .then(module => {
                    module.default();
                });
            break;
        case "admin-arenas-users":
            import("./pages/admin/arenaUsers")
                .then(module => {
                    module.default();
                });
            break;
        case "blog_create":
        case "blog_edit":
        case "blog_edit_translation":
            import("./pages/blog/edit")
                .then(module => {
                    module.default();
                });
            break;
        case "blog_post":
            import("./pages/blog/post")
                .then(module => {
                    module.default();
                });
            break;
        case 'booking-settings':
            import('./pages/admin/bookingSettings')
                .then(module => {
                    module.default();
                });
            break;
        case 'booking':
            import('./pages/booking/booking')
                .then(module => {
                    module.default();
                });
            break;
        case 'admin-booking':
            import('./pages/admin/bookings')
                .then(module => {
                    module.default();
                });
            break;
        case 'admin-booking-edit':
        case 'admin-booking-create':
            import('./pages/admin/bookingForm')
                .then(module => {
                    module.default();
                });
            break;
    }
}
