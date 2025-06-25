import { ArcElement, CategoryScale, Chart, Colors, DoughnutController, Legend, LinearScale, Tooltip } from "chart.js";
import { graphColors, labelColors } from "../../pages/user/profile/constants";
import ChartDeferred from "chartjs-plugin-deferred";
import ChartDataLabels from "chartjs-plugin-datalabels";
import { getUserModes } from "../../api/endpoints/userStats";

Chart.register(
    Colors,
    LinearScale,
    Legend,
    CategoryScale,
    DoughnutController,
    ArcElement,
    Tooltip,
    ChartDeferred,
    ChartDataLabels,
);

export default class GameModesCountChart {
    public dateRange: string = "month";
    private chart: Chart;
    private readonly canvas: HTMLCanvasElement;
    private readonly code: string;

    constructor(canvas: HTMLCanvasElement, code: string) {
        this.canvas = canvas;
        this.code = code;
        this.initChart();
    }

    async load(dateRange : string|null = null): Promise<void> {
        if (dateRange) {
            this.dateRange = dateRange;
        }
        const response = await getUserModes(this.code, this.dateRange);
        this.chart.data.labels = [];
        this.chart.data.datasets[0].data = [];
        Object.entries(response).forEach(([label, count]) => {
            this.chart.data.labels.push(label);
            this.chart.data.datasets[0].data.push(count);
        });
        this.update();

    }

    update(): void {
        this.chart.update();
    }

    private initChart(): void {
        this.chart = new Chart(this.canvas, {
            type: "doughnut",
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: graphColors,
                    borderWidth: 0,
                    datalabels: {
                        anchor: 'center'
                    }
                }]
            },
            options: {
                interaction: {
                    intersect: false,
                    mode: "index"
                },
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "bottom"
                    },
                    datalabels: {
                        // @ts-ignore
                        backgroundColor: function(context) {
                            return context.dataset.backgroundColor;
                        },
                        borderColor: 'white',
                        borderRadius: 25,
                        borderWidth: 2,
                        color: labelColors(),
                        font: {
                            weight: 'bold'
                        },
                        padding: 6,
                        formatter: (value: number) => value.toLocaleString(),
                        display: context => (context.dataset.data[context.dataIndex] as number) > 0,
                    }
                }
            }
        });
    }
}