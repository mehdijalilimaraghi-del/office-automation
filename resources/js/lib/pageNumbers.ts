export type PageNumberFormat = 'number_of' | 'slash' | 'short' | 'page';

export function formatPageNumber(format: PageNumberFormat, page: number, pages: number, appendix = false): string {
    const formatter = new Intl.NumberFormat('fa-IR');
    const current = formatter.format(page), total = formatter.format(pages);
    const value = format === 'slash' ? current + '/' + total
        : format === 'short' ? 'ص' + current + ' از ' + total
        : format === 'page' ? 'صفحه ' + current + ' از ' + total
        : current + ' از ' + total;
    return appendix ? 'پیوست ـ ' + value : value;
}