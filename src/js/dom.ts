export function byId<T extends HTMLElement = HTMLElement>(id: string): T | null {
    return document.getElementById(id) as T | null;
}

export function setText(id: string, value: string): void {
    const element = byId(id);
    if (element) element.innerText = value;
}

export function setHtml(id: string, value: string): void {
    const element = byId(id);
    if (element) element.innerHTML = value;
}

export function setStyle(id: string, property: string, value: string): void {
    const element = byId(id);
    if (element) element.style.setProperty(property.replace(/[A-Z]/g, (match: string) => `-${match.toLowerCase()}`), value);
}
