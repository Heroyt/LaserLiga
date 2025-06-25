import {
    Chart,
    Colors,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    TimeScale,
    Tooltip
} from "chart.js";
import "chartjs-adapter-date-fns";
import zoomPlugin from "chartjs-plugin-zoom";
import ChartDeferred from "chartjs-plugin-deferred";
import { graphColors, labelColors } from "../../pages/user/profile/constants";
import findDateFnsLocale from "./dateFnsLocale";
import { getUserRankHistory } from "../../api/endpoints/userStats";
import ChartDataLabels from "chartjs-plugin-datalabels";

Chart.register(
    Colors,
    LinearScale,
    LineController,
    Legend,
    LineElement,
    PointElement,
    TimeScale,
    Tooltip,
    zoomPlugin,
    ChartDeferred,
    ChartDataLabels
);
export default class RankHistoryChart {
    public dateRange: string = 'month';
    private chart: Chart;
    private readonly code: string;
    private compareUser: string = '';
    private compareEnabled: boolean = false;
    private compareBtn: HTMLButtonElement;

    constructor(canvas: HTMLCanvasElement, code: string, compareBtn : HTMLButtonElement) {
        this.code = code;
        this.compareBtn = compareBtn;
        this.initChart(canvas);
    }

    toggleCompare(compareUser : string): void {
        this.compareUser = compareUser;
        this.compareEnabled = !this.compareEnabled;
        this.load();
    }

    async load(dateRange : string|null = null): Promise<void> {
        if (dateRange) {
            this.dateRange = dateRange;
        }
        if (!this.chart) { // Still waiting to be initialized
            setTimeout(() => {
                this.load(dateRange);
            }, 100);
            return;
        }
        const response = await getUserRankHistory(this.code, this.dateRange);
        this.chart.data.labels = [];
        this.chart.data.datasets[0].data = [];
        Object.entries(response).forEach(([date, count]) => {
            // @ts-ignore
            this.chart.data.datasets[0].data.push({x: date, y: count});
        });
        if (this.compareEnabled && this.compareUser) {
            if (this.chart.data.datasets[1]) {
                this.chart.show(1);
            }
            try {
                const response = await getUserRankHistory(this.compareUser, this.dateRange);
                this.compareBtn.classList.remove("btn-outline-info");
                this.compareBtn.classList.add("btn-info");
                this.chart.data.datasets[1] = {
                    label: this.compareBtn.dataset.label,
                    data: [],
                    tension: 0.1,
                    borderColor: graphColors[0]
                };
                this.chart.data.datasets[1].data = [];
                Object.entries(response).forEach(([date, count]) => {
                    // @ts-ignore
                    this.chart.data.datasets[1].data.push({ x: date, y: count });
                });
            } catch (e) {
            }
        } else if (this.chart.data.datasets[1]) {
            this.compareBtn.classList.remove("btn-info");
            this.compareBtn.classList.add("btn-outline-info");
            this.chart.hide(1);
        }
        this.update();
    }

    update() : void {
        this.chart.update();
        this.chart.resetZoom();
    }

    private async initChart(canvas: HTMLCanvasElement): Promise<void> {
        this.chart = new Chart(canvas, {
            type: "line",
            data: {
                labels: ["Skill"],
                datasets: [{
                    label: canvas.dataset.label,
                    data: [],
                    tension: 0.1,
                    borderColor: graphColors[1],
                    pointRadius: 5
                }]
            },
            options: {
                interaction: {
                    intersect: false,
                    mode: "index"
                },
                maintainAspectRatio: false,
                plugins: {
                    datalabels: {
                        // @ts-ignore
                        backgroundColor: (context) =>
                            context.dataset.backgroundColor,
                        borderRadius: 4,
                        color: labelColors(),
                        font: {
                            weight: 'bold'
                        },
                        padding: 6,
                        formatter: value => value.y.toLocaleString(),
                        display: context => {
                            const maxCount = Math.floor(context.dataset.data.length / 30);
                            return context.dataIndex % maxCount === 0 || context.dataIndex === context.dataset.data.length - 1;
                        },
                    },
                    legend: {
                        display: false
                    },
                    zoom: {
                        zoom: {
                            wheel: {
                                enabled: true
                            },
                            pinch: {
                                enabled: true,
                            },
                            mode: "x"
                        },
                        pan: {
                            enabled: false,
                            mode: "x"
                        },
                        limits: {
                            x: {min: "original", max: "original"},
                        }
                    }
                },
                scales: {
                    x: {
                        type: "time",
                        time: {
                            unit: "day"
                        },
                        adapters: {
                            date: {
                                locale: await findDateFnsLocale()
                            }
                        }
                    }
                }
            }
        });
    }
}