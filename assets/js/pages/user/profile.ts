export default function initProfile() {
    const generalTabBtn = document.getElementById('general-stats-tab-control') as HTMLAnchorElement | null;
    const generalTabWrapper = document.getElementById('general-stats-tab') as HTMLDivElement | null;
    if (generalTabBtn && generalTabWrapper) {
        import(
            /* webpackChunkName: "profile-general" */
            './profile/general'
            ).then(module => {
            module.default(generalTabBtn, generalTabWrapper);
        });
    }

    const compareTabBtn = document.getElementById('compare-stats-tab-control') as HTMLAnchorElement | null;
    const compareTabWrapper = document.getElementById('compare-stats-tab') as HTMLDivElement | null;
    if (compareTabBtn && compareTabWrapper) {
        import(
            /* webpackChunkName: "profile-compare" */
            './profile/compare'
            ).then(module => {
            module.default(compareTabBtn, compareTabWrapper);
        });
    }

    const trendsTabBtn = document.getElementById('trends-stats-tab-control') as HTMLAnchorElement | null;
    const trendsTabWrapper = document.getElementById('trends-stats-tab') as HTMLDivElement | null;
    if (trendsTabBtn && trendsTabWrapper) {
        import(
            /* webpackChunkName: "profile-trends" */
            './profile/trends'
            ).then(module => {
            module.default(trendsTabBtn, trendsTabWrapper);
        });
    }

    const trophiesTabBtn = document.getElementById('trophies-stats-tab-control') as HTMLAnchorElement | null;
    const trophiesTabWrapper = document.getElementById('trophies-stats-tab') as HTMLDivElement | null;
    if (trophiesTabBtn && trophiesTabWrapper) {
        import(
            /* webpackChunkName: "profile-trophies" */
            './profile/trophies'
            ).then(module => {
            module.default(trophiesTabBtn, trophiesTabWrapper);
        });
    }

    const achievementsTabBtn = document.getElementById('achievements-stats-tab-control') as HTMLAnchorElement | null;
    const achievementsTabWrapper = document.getElementById('achievements-stats-tab') as HTMLDivElement | null;
    if (achievementsTabBtn && achievementsTabWrapper) {
        import(
            /* webpackChunkName: "profile-achievements" */
            './profile/achievements'
            ).then(module => {
            module.default(achievementsTabBtn, achievementsTabWrapper);
        });
    }

    const graphsTabBtn = document.getElementById('graphs-stats-tab-control') as HTMLAnchorElement | null;
    const graphsTabWrapper = document.getElementById('graphs-stats-tab') as HTMLDivElement | null;
    if (graphsTabBtn && graphsTabWrapper) {
        import(
            /* webpackChunkName: "profile-graphs" */
            './profile/graphs'
            ).then(module => {
            module.default(graphsTabBtn, graphsTabWrapper);
        });
    }
}