import { mountChart, readConfig } from '../helpers';
import { axisLabels, baseOptions, categoryLabelWidth, chartOptions, isNarrow, rowsHeight, seriesColor, textColor } from '../smk3';

const AXIS_MAX = 120;

/** Score per department on a fixed axis with the target line; the colour is the Nilai SMK3. */
export default (el) => {
    const { categories, values, satisfactory, ratings, target, targetLabel, seriesName, ratingLabel } = readConfig(el);
    const number = new Intl.NumberFormat(document.documentElement.lang, { maximumFractionDigits: 2 });

    mountChart(el, (dark) => ({
        ...baseOptions(dark),
        series: [{ name: seriesName, data: values }],
        colors: satisfactory.map((good) => seriesColor(good ? 'closed' : 'kritis', dark)),
        chart: chartOptions(rowsHeight(categories.length, 60)),
        plotOptions: {
            bar: {
                horizontal: true,
                distributed: true,
                barHeight: '46%',
                borderRadius: 4,
                borderRadiusApplication: 'end',
                dataLabels: { position: 'top' },
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (value) => `${number.format(value)}%`,
            textAnchor: 'start',
            offsetX: 6,
            style: { colors: [textColor(dark)], fontFamily: 'Outfit, sans-serif', fontSize: '12px', fontWeight: 500 },
            dropShadow: { enabled: false },
        },
        legend: { show: false },
        xaxis: {
            categories,
            min: 0,
            // the unlabelled room past 100% keeps the value labels of high scores outside their bars
            max: AXIS_MAX,
            tickAmount: isNarrow(el) ? 3 : 6,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { ...axisLabels(dark), formatter: (value) => (value > 100 ? '' : `${Math.round(value)}%`) },
        },
        yaxis: { labels: { ...axisLabels(dark), maxWidth: categoryLabelWidth(el) } },
        annotations: {
            xaxis: [
                {
                    x: target,
                    borderColor: textColor(dark),
                    strokeDashArray: 0,
                    label: {
                        text: targetLabel,
                        orientation: 'horizontal',
                        position: 'top',
                        borderWidth: 0,
                        offsetY: -8,
                        style: { background: 'transparent', color: textColor(dark), fontFamily: 'Outfit, sans-serif', fontSize: '11px' },
                    },
                },
            ],
        },
        tooltip: {
            ...baseOptions(dark).tooltip,
            y: {
                formatter: (value, { dataPointIndex }) => `${number.format(value)}% · ${ratingLabel}: ${ratings[dataPointIndex]}`,
            },
        },
    }));
};
