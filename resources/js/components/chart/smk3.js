// Colours and options shared by the SMK3 dashboard charts. Series colours match the badges of the
// same meaning (categoryColor(), Open / Closed) and were checked for colour-blind separation.
const SERIES_COLORS = {
    kritis: { light: '#D92D20', dark: '#F04438' }, // error
    major: { light: '#F79009', dark: '#F79009' }, // warning
    minor: { light: '#0BA5EC', dark: '#0BA5EC' }, // blue-light
    none: { light: '#98A2B3', dark: '#667085' }, // gray
    closed: { light: '#039855', dark: '#039855' }, // success
    open: { light: '#F79009', dark: '#F79009' }, // warning
    noPhoto: { light: '#F79009', dark: '#F79009' }, // warning: still open, nothing done yet
    withPhoto: { light: '#00549C', dark: '#4C87BA' }, // brand
};

export const seriesColor = (key, dark) => SERIES_COLORS[key][dark ? 'dark' : 'light'];

export const textColor = (dark) => (dark ? '#98A2B3' : '#475467');

/** Options every chart shares; the card colour draws the 2px gap between touching bars. */
export const baseOptions = (dark) => ({
    dataLabels: { enabled: false },
    fill: { opacity: 1 },
    stroke: { show: true, width: 2, colors: [dark ? '#171F2E' : '#FFFFFF'] },
    grid: {
        borderColor: dark ? '#1D2939' : '#F2F4F7',
        strokeDashArray: 0,
    },
    tooltip: { theme: dark ? 'dark' : 'light' },
    legend: {
        position: 'top',
        horizontalAlign: 'left',
        fontFamily: 'Outfit, sans-serif',
        labels: { colors: textColor(dark) },
        markers: { size: 5, shape: 'square', strokeWidth: 0 },
    },
});

export const axisLabels = (dark) => ({
    style: { colors: textColor(dark), fontFamily: 'Outfit, sans-serif', fontSize: '12px' },
});

/** Phone-sized charts get shorter department labels and fewer axis ticks. */
export const isNarrow = (el) => el.clientWidth < 480;

/** Longest department label before it is cut off with "…"; the table view has the full name. */
export const categoryLabelWidth = (el) => (isNarrow(el) ? 110 : 180);

/** No entry animation: every filter change redraws all charts. */
export const chartOptions = (height) => ({
    type: 'bar',
    height,
    fontFamily: 'Outfit, sans-serif',
    toolbar: { show: false },
    animations: { enabled: false },
});

/** Room per department, so bars stay thin however many departments are listed. */
export const rowsHeight = (rows, extra) => rows * 44 + extra;
