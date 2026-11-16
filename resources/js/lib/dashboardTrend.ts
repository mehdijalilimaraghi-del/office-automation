export interface ActivityDay {
    date: string;
    created: number;
    completed: number;
}

export type TrendPeriod = 'week' | 'month' | 'season' | 'year';

const calendar = new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-latn', {
    year: 'numeric', month: 'numeric', day: 'numeric', timeZone: 'UTC',
});
const dayLabel = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { month: 'numeric', day: 'numeric', timeZone: 'UTC' });
const months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
export const persianNumber = new Intl.NumberFormat('fa-IR');

export function dashboardTrend(days: ActivityDay[], period: TrendPeriod): (ActivityDay & { label: string })[] {
    const dates = days.map(point => {
        const date = new Date(point.date + 'T00:00:00Z');
        const parts = calendar.formatToParts(date);
        const number = (type: 'year' | 'month' | 'day') => Number(parts.find(part => part.type === type)?.value);
        return { ...point, calendarDate: date, year: number('year'), month: number('month'), day: number('day') };
    });
    const today = dates.at(-1);
    if (!today) return [];
    if (period === 'week') {
        return dates.slice(-7).map(point => ({ ...point, label: dayLabel.format(point.calendarDate) }));
    }

    const groups = new Map<number, ActivityDay & { label: string }>();
    const seasonStart = Math.floor((today.month - 1) / 3) * 3 + 1;
    for (const point of dates) {
        if (point.year !== today.year
            || (period === 'month' && point.month !== today.month)
            || (period === 'season' && point.month < seasonStart)) continue;
        const index = period === 'month' ? Math.floor((point.day - 1) / 7) : point.month;
        const group = groups.get(index) ?? { date: point.date, label: '', created: 0, completed: 0 };
        group.label = period === 'month'
            ? persianNumber.format(index * 7 + 1) + ' تا ' + persianNumber.format(point.day)
            : months[point.month - 1];
        group.created += point.created;
        group.completed += point.completed;
        groups.set(index, group);
    }
    return [...groups.values()];
}
