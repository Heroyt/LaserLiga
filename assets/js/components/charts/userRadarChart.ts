import { Chart, Colors, Legend, PointElement, RadarController, RadialLinearScale, TimeScale, Tooltip } from "chart.js";
import ChartDeferred from "chartjs-plugin-deferred";
import { getUserRadar } from "../../api/endpoints/userStats";
import ChartDataLabels from "chartjs-plugin-datalabels";

Chart.register(
    RadarController,
    PointElement,
    RadialLinearScale,
    Colors,
    TimeScale,
    Legend,
    Tooltip,
    ChartDeferred,
    ChartDataLabels
);

export default class UserRadarChart {
    private chart: Chart;
    private readonly canvas: HTMLCanvasElement;
    private readonly code: string;
    private readonly compareUser: string = '';

    constructor(canvas: HTMLCanvasElement, code: string, compareUser: string = '') {
        this.canvas = canvas;
        this.code = code;
        this.compareUser = compareUser;
        this.initChart();
    }

    async load(): Promise<void> {
        const response = await getUserRadar(
            this.code,
            this.compareUser ? this.compareUser : null
        );
        this.chart.data.datasets = [];
        Object.entries(response).forEach(([label, values]) => {
            this.chart.data.datasets.push({
                label,
                data: Object.values(values),
            });
        });
        this.update();
    }

    update(): void {
        this.chart.update();
    }

    private initChart(): void {
        const radarCategories: { [index: string]: string } = JSON.parse(this.canvas.dataset.categories);
        this.chart = new Chart(this.canvas, {
            type: "radar",
            data: {
                labels: Object.values(radarCategories),
                datasets: [],
            },
            options: {
                interaction: {
                    intersect: false,
                    mode: "index"
                },
                parsing: {
                    key: 'value'
                },
                maintainAspectRatio: false,
                responsive: true,
                elements: {
                    line: {
                        borderWidth: 2,
                    }
                },
                scales: {
                    r: {
                        grid: {
                            display: true, color: '#777',
                        },
                        angleLines: {
                            display: true, color: '#aaa',
                        },
                        ticks: {
                            backdropColor: null, color: '#aaa',
                        },
                        suggestedMin: 0,
                        suggestedMax: 100
                    }
                },
                plugins: {
                    datalabels: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                console.log(context);
                                let label = context.dataset.label || '';

                                if (label) {
                                    label += ': ';
                                }
                                // @ts-ignore
                                if (context.raw.label) {
                                    // @ts-ignore
                                    label += context.raw.label;
                                } else if (context.parsed.r !== null) {
                                    label += context.parsed.r.toLocaleString();
                                }
                                // @ts-ignore
                                if (context.raw.percentileLabel) {
                                    // @ts-ignore
                                    label += ` (${context.raw.percentileLabel})`
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }}