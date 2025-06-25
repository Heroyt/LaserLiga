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
import { getUserOrderHistory } from "../../api/endpoints/userStats";
import zoomPlugin from "chartjs-plugin-zoom";
import ChartDeferred from "chartjs-plugin-deferred";
import findDateFnsLocale from "./dateFnsLocale";
import { graphColors } from "../../pages/user/profile/constants";
import ChartDataLabels from "chartjs-plugin-datalabels";

Chart.register(
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    Colors,
    TimeScale,
    Legend,
    Tooltip,
    zoomPlugin,
    ChartDeferred,
    ChartDataLabels
);
export default class RankOrderChart {
    dateRange: string = "month";
    private chart: Chart;
    private readonly canvas: HTMLCanvasElement;
    private readonly code: string;

    constructor(canvas: HTMLCanvasElement, code: string) {
        this.canvas = canvas;
        this.code = code;
        this.initChart();
    }

    async load(dateRange: string | null = null): Promise<void> {
        if (dateRange) {
            this.dateRange = dateRange;
        }
        const response = await getUserOrderHistory(this.code, this.dateRange);
        this.chart.data.labels = [];
        this.chart.data.datasets[0].data = [];
        Object.entries(response).forEach(([date, values]) => {
            // @ts-ignore
            this.chart.data.datasets[0].data.push({ x: date, y: values.position });
        });
        this.update();
    }

    update(): void {
        this.chart.update();
        this.chart.resetZoom();
    }

    private async initChart(): Promise<void> {
        this.chart = new Chart(this.canvas, {
            type: "line",
            data: {
                labels: [],
                datasets: [
                    {
                        label: this.canvas.dataset.label,
                        data: [],
                        borderColor: graphColors[1],
                        pointRadius: 5
                    }
                ]
            },
            options: {
                interaction: {
                    intersect: false,
                    mode: "index"
                },
                maintainAspectRatio: false,
                responsive: true,
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
                    },
                    y: {
                        reverse: true,
                        min: 1
                    }
                },
                plugins: {
                    datalabels: {
                        // @ts-ignore
                        backgroundColor: (context) =>
                            context.dataset.backgroundColor,
                        borderRadius: 4,
                        color: 'white',
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
                    zoom: {
                        zoom: {
                            wheel: {
                                enabled: true
                            },
                            pinch: {
                                enabled: true
                            },
                            mode: "x"
                        },
                        pan: {
                            enabled: false,
                            mode: "x"
                        },
                        limits: {
                            x: { min: "original", max: "original" }
                        }
                    }
                }
            }
        });
    }
}