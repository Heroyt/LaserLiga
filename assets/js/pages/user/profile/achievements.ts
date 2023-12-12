import {startLoading, stopLoading} from "../../../loaders";
import axios, {AxiosResponse} from "axios";
import {initTooltips} from "../../../functions";

interface Achievement {
    id: number;
    icon: string | null;
    name: string;
    description: string;
    info: string | null;
    type: string;
    rarity: string;
    key: string | null;
    group: boolean;
    title: null | { id: number, name: string },
}

interface AchievementClaimDto {
    achievement: Achievement;
    icon: string;
    claimed: boolean;
    code: string | null;
    dateTime: { date: string, timezone_type: number, timezone: string } | null;
    totalCount: number;
}

let achievementsLoaded = false;

export default function initAchievementTab(achievementsTabBtn: HTMLAnchorElement, achievementsTabWrapper: HTMLDivElement) {
    const achievementsLoaderWrapper = document.getElementById('achievements-loader') as HTMLDivElement;
    const achievementsStatsWrapper = document.getElementById('achievements-stats') as HTMLDivElement;
    const achievementsWrapper = document.getElementById('achievements-wrapper') as HTMLDivElement;
    const achievementsUnclaimedWrapper = document.getElementById('achievements-unclaimed-wrapper') as HTMLDivElement;

    const achievementsClaimedCount = achievementsTabWrapper.querySelector('.achievements-claimed-count') as HTMLSpanElement;
    const achievementsCount = achievementsTabWrapper.querySelector('.achievements-count') as HTMLSpanElement;

    const playerCount = parseInt(achievementsStatsWrapper.dataset.playerCount);
    const claimLabel = achievementsWrapper.dataset.claimLabel;
    const percentageLabel = achievementsWrapper.dataset.percentageLabel;
    const code = achievementsTabBtn.dataset.user;
    if (achievementsTabWrapper.classList.contains('show')) {
        updateAchievements();
    }
    achievementsTabBtn.addEventListener('show.bs.tab', e => {
        if (achievementsLoaded) {
            return; // Do not load data more than once
        }
        updateAchievements();
    });

    function updateAchievements() {
        startLoading(true);
        achievementsWrapper.innerHTML = '';
        achievementsUnclaimedWrapper.innerHTML = '';
        axios.get('/user/' + code + '/stats/achievements')
            .then((response: AxiosResponse<AchievementClaimDto[]>) => {
                stopLoading(true, true);
                achievementsLoaderWrapper.classList.add('d-none');
                achievementsStatsWrapper.classList.remove('d-none');

                achievementsCount.innerText = response.data.length.toString();

                const achievementGroups: Map<string, HTMLDivElement> = new Map;
                let claimed = 0;
                response.data.forEach(achievement => {
                    const achievementEl = document.createElement('div');
                    achievementEl.classList.add('achievement-card', 'm-2', 'rarity-' + achievement.achievement.rarity, achievement.claimed ? 'achievement-claimed' : 'achievement-unclaimed');
                    achievementEl.id = 'achievement-' + achievement.achievement.id.toString();
                    let infoBtn = '';
                    if (achievement.achievement.info) {
                        infoBtn = `<button class="btn p-0" type="button" data-toggle="tooltip" title="${achievement.achievement.info}"><i class="fa-solid fa-circle-question fs-5" style="line-height: 1.2rem;vertical-align: middle;"></i></button>`;
                    }
                    achievementEl.innerHTML = '';
                    if (achievement.achievement.title) {
                        achievementEl.innerHTML += `<i class="fa-solid fa-tag" data-toggle="tooltip" title="Ocenění odemyká titul."></i>`;
                    }
                    achievementEl.innerHTML += `${achievement.icon}<h4 class="title">${achievement.achievement.name}</h4><p class="description">${achievement.achievement.description}${infoBtn}</p>`;
                    achievementEl.innerHTML += `<p class="claim-percent">${percentageLabel.replace('%s', (achievement.totalCount / playerCount).toLocaleString(undefined, {
                        style: 'percent',
                        maximumFractionDigits: 2
                    }))}</p>`;
                    if (achievement.claimed && achievement.dateTime && achievement.code) {
                        claimed++;
                        const date = new Date(achievement.dateTime.date);
                        achievementEl.innerHTML += `<div class="claim-info">${claimLabel}: <a href="/g/${achievement.code}" class="btn btn-secondary">${date.toLocaleDateString()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}</a></div>`;
                    }

                    if (!achievement.claimed) {
                        achievementsUnclaimedWrapper.appendChild(achievementEl);
                    } else if (achievement.achievement.group) {
                        let group = achievementGroups.get(achievement.achievement.type);
                        if (!group) {
                            group = document.createElement('div');
                            achievementGroups.set(achievement.achievement.type, group);
                            group.classList.add('achievement-group', 'm-3');
                            group.dataset.group = achievement.achievement.type;
                            group.setAttribute('data-group', achievement.achievement.type);
                            achievementsWrapper.appendChild(group);

                            // Rotate cards on click
                            group.addEventListener('click', e => {
                                if (e.target instanceof HTMLAnchorElement || e.target instanceof HTMLButtonElement || group.childElementCount < 2) {
                                    return;
                                }

                                const el = group.lastElementChild;
                                el.classList.add('move-back', 'animating');

                                setTimeout(() => {
                                    group.prepend(el);
                                    setTimeout(() => {
                                        el.classList.remove('move-back');
                                        setTimeout(() => {
                                            el.classList.remove('animating');
                                        }, 300);
                                    }, 5);
                                }, 200)
                            });
                        }
                        group.appendChild(achievementEl);
                    } else {
                        achievementsWrapper.appendChild(achievementEl);
                    }
                });
                achievementsClaimedCount.innerText = claimed.toString();
                initTooltips(achievementsWrapper);
                initTooltips(achievementsUnclaimedWrapper);

                achievementsLoaded = true;
            })
            .catch(e => {
                console.error(e);
                stopLoading(false, true);
            });
    }
}