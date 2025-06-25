import { BarController, BarElement, CategoryScale, Chart, Colors, Legend, LinearScale, Tooltip } from "chart.js";
import { getUserGameCounts } from "../../api/endpoints/userStats";
import { graphColors, labelColors } from "../../pages/user/profile/constants";
import ChartDeferred from "chartjs-plugin-deferred";
import ChartDataLabels from "chartjs-plugin-datalabels";

Chart.register(
    LinearScale,
    CategoryScale,
    Colors,
    Legend,
    BarController,
    BarElement,
    Tooltip,
    ChartDeferred,
    ChartDataLabels
);

export default class GameCountChart {
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
        const response = await getUserGameCounts(this.code, this.dateRange);
        let datasets = new Map();
        this.chart.data.labels = [];
        let i = 0;
        Object.values(response).forEach((values) => {
            this.chart.data.labels.push(values.label);
            values.modes.forEach(modeData => {
                if (!datasets.has(modeData.id_mode)) {
                    datasets.set(modeData.id_mode, {
                        label: modeData.modeName,
                        backgroundColor: graphColors[i % graphColors.length],
                        data: [],
                    })
                    i++;
                }
                let data = datasets.get(modeData.id_mode);
                data.data.push(modeData.count);
                datasets.set(modeData.id_mode, data);
            });
        });
        this.chart.data.datasets = Array.from(datasets.values());
        this.update();
    }

    update(): void {
        this.chart.update();
    }

    private initChart(): void {
        this.chart = new Chart(this.canvas, {
            type: "bar",
            data: {
                labels: [],
                datasets: []
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
                        stacked: true
                    },
                    y: {
                        stacked: true
                    }
                },
                plugins: {
                    datalabels: {
                        display: context => (context.dataset.data[context.dataIndex] as number) > 0,
                        color: labelColors(),
                        font: {
                            weight: 'bold'
                        },
                    }
                }
            }
        });
    }
}