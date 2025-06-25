export const graphColors = [
    'rgb(255, 99, 132)',
    'rgb(54, 162, 235)',
    'rgb(255, 205, 86)',
    'rgb(86,255,89)',
    'rgb(128,86,255)',
    'rgb(86,255,190)',
    'rgb(255,137,86)',
    'rgb(238,86,255)',
    'rgb(73,101,215)',
    'rgb(208,55,55)',
    'rgb(57,190,36)',
];

export function labelColors() : string[] {
    return graphColors.map(labelColor);
}

export function labelColor(background: string): string {
    const rgb = background.match(/\d+/g);
    if (!rgb) return 'black';
    const [r, g, b] = rgb.map(Number);
    const brightness = (r * 299 + g * 587 + b * 114) / 1000;
    return brightness > 125 ? 'black' : 'white';
}