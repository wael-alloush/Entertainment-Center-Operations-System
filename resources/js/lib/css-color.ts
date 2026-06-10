const DEFAULT_HEX_COLOR = '#6366f1';

export function cssColorToHex(color: string, fallback: string = DEFAULT_HEX_COLOR): string {
    if (/^#[0-9A-Fa-f]{6}$/i.test(color)) {
        return color.toLowerCase();
    }

    if (/^#[0-9A-Fa-f]{3}$/i.test(color)) {
        const [, red, green, blue] = color;

        return `#${red}${red}${green}${green}${blue}${blue}`.toLowerCase();
    }

    if (typeof document === 'undefined') {
        return fallback;
    }

    const element = document.createElement('span');
    element.style.display = 'none';
    element.style.color = color;
    document.body.appendChild(element);

    const computed = getComputedStyle(element).color;
    document.body.removeChild(element);

    const match = computed.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/);

    if (!match) {
        return fallback;
    }

    return `#${[match[1], match[2], match[3]]
        .map((value) => Number(value).toString(16).padStart(2, '0'))
        .join('')}`;
}
