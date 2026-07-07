export function formatNumber(value, options = {}) {
    return Number(value).toLocaleString("de-DE", options);
}

export function formatFixed1(value) {
    return formatNumber(value, { minimumFractionDigits: 1 });
}

export function formatDateParam(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

export function addDays(date, days) {
    const nextDate = new Date(date);
    nextDate.setDate(nextDate.getDate() + days);
    return nextDate;
}

export function formatTimeOnly(value) {
    return new Date(value).toLocaleTimeString("de-DE", {
        hour: "2-digit",
        minute: "2-digit",
    });
}
