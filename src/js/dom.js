export function byId(id) {
    return document.getElementById(id);
}

export function setText(id, value) {
    const element = byId(id);
    if (element) element.innerText = value;
}

export function setHtml(id, value) {
    const element = byId(id);
    if (element) element.innerHTML = value;
}

export function setStyle(id, property, value) {
    const element = byId(id);
    if (element) element.style[property] = value;
}
