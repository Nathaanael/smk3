import { mountChart, readConfig } from '../helpers';
import { axisLabels, baseOptions, categoryLabelWidth, chartOptions, isNarrow, rowsHeight, seriesColor } from '../smk3';

/** Finding counts in stacked bars (`horizontal`) or columns; used by three dashboard charts. */
export default (el) => {
    const { categories, series, horizontal, rows = categories.length } = readConfig(el);

    const highest = Math.max(1, ...categories.map((_, index) => series.reduce((sum, { data }) => sum + data[index], 0)));
    // counts are whole numbers: small totals get one tick per finding instead of 0, 0.5, 1
    const countAxis = {
        ...(highest <= 5 ? { max: highest, tickAmount: highest } : { tickAmount: horizontal && isNarrow(el) ? 3 : 5 }),
        min: 0,
    };
    const count = (value) => Math.round(value).toString();

    // column slots shrink with the number of months, so keep the column itself about 24px wide
    const columnWidth = () => `${Math.min(70, Math.round((24 / (el.clientWidth / categories.length)) * 100))}%`;

    mountChart(el, (dark) => ({
        ...baseOptions(dark),
        series: series.map(({ name, data }) => ({ name, data })),
        colors: series.map(({ key }) => seriesColor(key, dark)),
        chart: { ...chartOptions(horizontal ? rowsHeight(rows, 90) : 340), stacked: true },
        plotOptions: {
            bar: {
                horizontal,
                // a chart stretched to match its neighbour keeps bars of the usual thickness
                barHeight: `${Math.round((46 * categories.length) / rows)}%`,
                columnWidth: columnWidth(),
                borderRadius: 4,
                borderRadiusApplication: 'end',
                borderRadiusWhenStacked: 'last',
            },
        },
        xaxis: {
            categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            // labels are only rotated when they would not fit side by side
            labels: { ...axisLabels(dark), ...(horizontal ? { formatter: count } : { rotate: -45, rotateAlways: el.clientWidth / categories.length < 80 }) },
            ...(horizontal ? countAxis : {}),
        },
        yaxis: {
            ...(horizontal ? {} : countAxis),
            labels: { ...axisLabels(dark), ...(horizontal ? { maxWidth: categoryLabelWidth(el) } : { formatter: count }) },
        },
        tooltip: {
            ...baseOptions(dark).tooltip,
            shared: true,
            intersect: false,
            y: { formatter: count },
        },
    }));
};
