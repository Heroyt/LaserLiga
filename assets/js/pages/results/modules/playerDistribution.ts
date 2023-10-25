import {Modal} from "bootstrap";
import {Chart} from "chart.js/auto";
import Annotation from "chartjs-plugin-annotation";
import {startLoading, stopLoading} from "../../../loaders";
import axios, {AxiosResponse} from "axios";
import {DistributionModuleInterface} from "../playerModules";

interface DistributionResponse {
    player: { [index: string]: any },
    distribution: { [index: string]: number },
    percentile: number,
    min: number,
    max: number,
    value: number,
}

Chart.register(Annotation);

export default class DistributionModule implements DistributionModuleInterface {

    selectedPlayer: number;
    selectedParam: string;

    private readonly modalDom: HTMLDivElement;
    private modal: Modal;
    private time: HTMLHeadingElement;
    private readonly canvas: HTMLCanvasElement;
    private dates: HTMLSelectElement;
    private chart: Chart;

    constructor() {
        // Distribution chart
        this.modalDom = document.getElementById('distribution-modal') as HTMLDivElement;
        this.modal = Modal.getOrCreateInstance(this.modalDom);
        this.time = document.getElementById('distribution-title') as HTMLHeadingElement;
        this.canvas = document.getElementById('distribution-chart') as HTMLCanvasElement;
        this.dates = document.getElementById('distribution-dates') as HTMLSelectElement;
        this.chart = new Chart(
            this.canvas,
            {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [],
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        },
                        x: {
                            type: 'category'
                        },
                        x1: {
                            type: 'linear',
                            position: 'bottom',
                            display: false,
                            suggestedMin: 0,
                            suggestedMax: 100,
                        }
                    },
                    plugins: {
                        annotation: {
                            annotations: {
                                percentile: {
                                    type: "line",
                                    scaleID: 'x1',
                                    value: 0,
                                    endValue: 0,
                                    borderColor: 'rgb(255, 99, 132)',
                                    borderWidth: 2,
                                    label: {
                                        display: true,
                                        borderWidth: 1,
                                        content: '',
                                        borderColor: 'rgb(255, 99, 132)',
                                        color: 'rgb(255, 99, 132)',
                                        textStrokeColor: 'rgb(255, 99, 132)',
                                    }
                                }
                            }
                        }
                    }
                },
            }
        )


        this.dates.addEventListener('change', () => {
            this.load();
        })
    }

    setTitle(title: string) {
        this.time.innerText = title;
    }

    async load() {
        if (!this.selectedParam || !this.selectedPlayer) {
            return;
        }
        _paq.push(['trackEvent', 'Results', 'PlayerDistribution', this.selectedParam, this.selectedPlayer]);
        startLoading();
        try {
            const response: AxiosResponse<DistributionResponse> = await axios.get(`/game/${gameCode}/player/${this.selectedPlayer}/distribution/${this.selectedParam}?dates=${this.dates.value}`);

            this.chart.data.labels = [];
            this.chart.data.datasets = [
                {
                    label: this.canvas.dataset.label,
                    data: [],
                },
            ];

            Object.entries(response.data.distribution).forEach(([group, count]) => {
                this.chart.data.labels.push(group);
                this.chart.data.datasets[0].data.push(count);
            });

            this.chart.update('reset');

            this.chart.options.scales.x1.min = response.data.min;
            this.chart.options.scales.x1.max = response.data.max;

            // @ts-ignore
            this.chart.options.plugins.annotation.annotations.percentile.value = response.data.value;
            // @ts-ignore
            this.chart.options.plugins.annotation.annotations.percentile.endValue = response.data.value;
            // @ts-ignore
            this.chart.options.plugins.annotation.annotations.percentile.label.content = response.data.percentile > 50 ? `${this.canvas.dataset.top} ${100 - response.data.percentile} %` : `${this.canvas.dataset.bottom} ${response.data.percentile} %`;

            console.log(this.chart.options.plugins.annotation, this.chart.data);
            this.chart.scales.x1.configure();
            this.chart.update();
            setTimeout(() => {
                this.chart.update();
            }, 500);
            stopLoading(true);
        } catch (e) {
            stopLoading(false);
        }
    }

    show(): void {
        this.modal.show();
    }

    hide(): void {
        this.modal.hide();
    }

}