const persianDateTimeFormatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
});

const persianDateFormatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

export const formatPersianDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.slice(0, 10).split('-').map(Number);

    if (!year || !month || !day) {
        return '—';
    }

    return persianDateFormatter.format(new Date(year, month - 1, day));
};

export const formatPersianDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const normalized = value.replace('T', ' ');
    const [datePart, timePart = '00:00'] = normalized.split(' ');
    const [year, month, day] = datePart.slice(0, 10).split('-').map(Number);
    const [hour, minute] = timePart.slice(0, 5).split(':').map(Number);

    if (!year || !month || !day) {
        return '—';
    }

    return persianDateTimeFormatter.format(
        new Date(year, month - 1, day, hour || 0, minute || 0),
    );
};
